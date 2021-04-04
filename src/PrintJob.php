<?php

	namespace ShaneMcC\PTouchPrint;

	use Exception;

	class PrintJob {
		private PTouchJobBinary $job;

		private int $autoCut;
		private bool $halfCut;
		private bool $chainPrinting;
		private int $tapeSize;
		private int $marginSize;

		private bool $hasImages = false;
		private int $jobState = PrintJob::JOBSTATE_NONE;

		public const JOBSTATE_NONE = 0;
		public const JOBSTATE_START = 1;
		public const JOBSTATE_READY = 3;

		/**
		 * Create a new PrintJob.
		 *
		 * @param int $tapeSize Tape size to use for job in mm (Default: 12)
		 * @param int $marginSize Margin size for pages (Default: 14)
		 * @param int $autoCut Auto cut after how many pages? (Default: 1 - 0 to disable)
		 * @param bool $halfCut Use half-cutting.
		 * @param bool $chainPrinting Use chain-printing.
		 */
		public function __construct(int $tapeSize = 12, int $marginSize = 14, int $autoCut = 1, bool $halfCut = true, bool $chainPrinting = false) {
			$this->autoCut = $autoCut;
			$this->halfCut = $halfCut;
			$this->chainPrinting = $chainPrinting;
			$this->tapeSize = $tapeSize;
			$this->marginSize = $marginSize;
		}


		/**
		 * Start a new print job.
		 *
		 * @return $this This for chaining.
		 */
		public function startJob(): PrintJob {
			$this->job = new PTouchJobBinary();
			$this->job->printInfo($this->tapeSize);
			$this->job->mode(($this->autoCut > 0));
			if ($this->autoCut > 0) {
				$this->job->cutEachX($this->autoCut);
			}
			$this->job->advancedMode($this->halfCut, $this->chainPrinting);
			$this->job->margin($this->marginSize);
			$this->job->compressionMode();
			$this->jobState = PrintJob::JOBSTATE_START;

			return $this;
		}

		/**
		 * Add images to our print job.
		 *
		 * @param array $images Array of RasterImages to print.
		 * @return $this This for chaining.
		 * @throws Exception If there is an error adding the image.
		 */
		public function addImages(Array $images): PrintJob {
			foreach ($images as $image) {
				$this->addImage($image);
			}

			return $this;
		}

		/**
		 * Add a single image to our print job.
		 *
		 * @param RasterImage $image Image to add to job.
		 * @return $this This for chaining.
		 * @throws Exception If there is an error adding the image.
		 */
		public function addImage(RasterImage $image): PrintJob {
			if ($this->jobState == PrintJob::JOBSTATE_READY) {
				throw new Exception('You can not add any more images to a ready job.');
			}
			if ($this->jobState != PrintJob::JOBSTATE_START) {
				throw new Exception('You must call startJob() before adding images.');
			}

			if ($this->hasImages) { $this->job->printPage(); }
			$this->job->rasterImage($image);
			$this->hasImages = true;

			return $this;
		}

		/**
		 * Start a new print job.
		 *
		 * @return $this This for chaining.
		 * @throws Exception
		 */
		public function endJob(): PrintJob {
			if (!$this->hasImages) {
				throw new Exception('You must add at least 1 image.');
			}

			$this->job->printAndFeed();

			$this->jobState = PrintJob::JOBSTATE_READY;
			return $this;
		}

		/**
		 * Print this job to a remote printer
		 *
		 * @param String $ip IP to print to
		 * @param int $port Port to print to (Default: 9100)
		 * @throws Exception If the job is not printable or there was an error with the printer
		 */
		public function printToIP(String $ip, $port = 9100) {
			if ($this->jobState != PrintJob::JOBSTATE_READY) {
				throw new Exception('You can not print an incomplete job.');
			}

			$fp = @fsockopen($ip, $port, $errno, $errstr, 30);
			if (!$fp) {
				throw new Exception('There was an error connecting to the printer: ' . $errstr . '(' . $errno . ')');
			} else {
				fwrite($fp, $this->job->getData());
				fclose($fp);
			}
		}

		/**
		 * Print this job to a local CUPS printer
		 *
		 * @param String $printerName Printer name to print to.
		 * @throws Exception If the job is not printable or there was an error with the printer
		 */
		public function printToCUPS(String $printerName) {
			if ($this->jobState != PrintJob::JOBSTATE_READY) {
				throw new Exception('You can not print an incomplete job.');
			}

			if (!file_exists('/usr/bin/lpr')) { throw new Exception('This requires /usr/bin/lpr to exist.'); }

			$descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

			$cmd = 'exec /usr/bin/lpr -P '. escapeshellarg($printerName) . ' 2>&1';
			$pipes = [];
			$proc = proc_open($cmd, $descriptorspec, $pipes);
			if ($proc) {
				fwrite($pipes[0], $this->job->getData());
				fclose($pipes[0]);
				fclose($pipes[1]);
				fclose($pipes[2]);
			} else {
				throw new Exception('Error opening LPR');
			}
		}

		/**
		 * Print this print job to a String
		 *
		 * @throws Exception If the job is not printable
		 */
		public function printToString(): String {
			if ($this->jobState != PrintJob::JOBSTATE_READY) {
				throw new Exception('You can not print an incomplete job.');
			}

			return $this->job->getData();
		}

		/**
		 * Print this print job to STDOUT
		 *
		 * @throws Exception If the job is not printable
		 */
		public function printToSTDOUT() {
			echo $this->printToString();
		}

		/**
		 * Print this print job to STDOUT as HEX for debugging.
		 *
		 * @throws Exception If the job is not printable
		 */
		public function printHEXToSTDOUT() {
			if ($this->jobState != PrintJob::JOBSTATE_READY) {
				throw new Exception('You can not print an incomplete job.');
			}

			$this->job->displayHex();
		}
	}
