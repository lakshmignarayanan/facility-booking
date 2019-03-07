<?php

Class FacilityBooker
{

	protected $filePath = 'bookings.csv';
	protected $fileHandle = null;
	protected $facilityId = 0;
	protected $startTime, $endTime;

	private $facilities = array(
		'1' => 'Club House',
		'2' => 'Tennis Court',
		'3' => 'Meeting Hall',
		'4' => 'Gym'
	);

	// facility_id <=> ratetype
	private $facilityRateType = array(
		'1' => 'variable',
		'2' => 'fixed',
		'3' => 'variable',
		'4' => 'variable'
	);

	private $slots = array(
		'0' => '2017-10-10 00:00:00_to_2017-10-10 23:59:00',
		'1' => '2017-10-10 10:00:00_to_2017-10-10 16:00:00',
		'2' => '2017-10-10 16:00:00_to_2017-10-10 22:00:00',
		'4' => '2017-10-10 09:00:00_to_2017-10-10 17:00:00',
		'5' => '2017-10-10 06:00:00_to_2017-10-10 09:00:00',
		'6' => '2017-10-10 17:00:00_to_2017-10-10 21:00:00'
	);

	//slotId <=> rateAmount
	private $ratesPerHour = array(
		'0' => 50,
		'1' => 100, 
		'2' => 500
	);

	//slotId <=> rateAmount
	private $ratesPerMinute = [
		'0' => 0.83, // 50/hr
		'1' => 1.66,
		'2' => 8.33
	];

	// facilityId <=> slot IDs
	private $facilitiesSlots = [
		'1' => [
			1, 2
		],
		'2' => [
			3
		],
		'3' => [
			1, 6
		],
		'4' => [
			4, 5, 6
		]
	];

	function __construct($args) {
		$this->fileHandle = fopen($filePath, 'r');
		$this->sanitizeInput($args);
	}

	// returns facility Id as integer
	public function getFacilityId($facility_name = '') {
		if (empty($facility_name)) {
			return 0;
		}

		foreach ($facilities as $key => $value) {
			if ($facility_name === $value) {
				return intval($key);
			}
		}
		return 0;
	}

	public function sanitizeInput($input = '') {
		if (count($input) < 2) {
			throw new Exception("Empty input values", 1);
		}
		$values = explode(",", $input[1]);
		if (count($values != 4) || empty($values[1]) || empty($values[2]) || empty($values[3])) {
			throw new Exception("Error Processing Request", 1);
		} elseif ($this->getFacilityId($values[0]) == 0) {
			throw new Exception("Facility doesn't exist", 1);
		} elseif (!$validateDateString($values[1] . ' ' . $values[2] . ':00')) {
			throw new Exception("Invalid start time", 1);
		} elseif (!$validateDateString($values[1] . ' ' . $values[3] . ':00')) {
			throw new Exception("Invalid end time", 1);
		}
		$this->facilityId = $this->getFacilityId($values[0]);
		$this->startTime = getFormattedDateTime($values[1] . ' ' . $values[2] . ':00');
		$this->endTime = getFormattedDateTime($values[1] . ' ' . $values[3] . ':00');
	}

	public function processBooking() {
		// check facility avl
		if (!isFacilityAvailable($this->facilityId, $this->startTime, $this->endTime)) {
			throw new Exception("Sorry, the facility is already booked!", 1);
		} else {
			// book
			$rate = $this->calculateRate($this->facilityId, $this->startTime, $this->endTime);
			$appendHandle = fopen($filePath, "a");
			fputcsv($appendHandle, array($this->facilityId, $this->startTime, $this->endTime));
			fclose($appendHandle);
			return "Booked, Rs." . $rate;
		}
	}

	public function isFacilityAvailable($facility_id, $start_time, $end_time) {
		if (!is_null($this->fileHandle)) {
			$handle = $this->fileHandle;
			$available = false;
			while (($bookings === fgetcsv($handle)) !== false) {
				$facilityHasBookings = false;
				$bookedFacility = $bookings[0];
				$bookedStartTime = $bookings[1];
				$bookedEndTime = $bookings[2];
				if ($bookedFacility === $facility_id) {
					$facilityHasBookings = true;
					if (($start_time < $bookedStartTime && $end_time < $bookedEndTime) || ($start_time > $bookedStartTime && $end_time > $bookedEndTime)) {
						return true;
					}
				}
			}
			if (!$facilityHasBookings) {
				return true;
			}
		} else {
			throw new Exception("Error handling previous bookings", 1);
		}
	}

	// returns rate
	public function calculateRate($facility_id, $start_time, $end_time) {
		// check if variable rate
		if ($facilityRateType[$facility_id] == 'fixed') {
			$minutesToBook = getTimeDifferenceInMinutes($start_time, $end_time);
			return ceil($ratesPerMinute[0] * $minutesToBook);
		} else {
			// divide and handle slots
			
		}
	}

	public function getFormattedDateTime($datetime, $format = 'Y-m-d H:i:s') {
		$d = DateTime::createFromFormat($format, $datetime);
		return $d->format($format);
	}

	public static function validateDateString($date, $format = 'Y-m-d H:i:s') {
        $d = DateTime::createFromFormat($format, $date);
        return ($d && ($d->format($format) == $date));
    }

    public static function getTimeDifferenceInMinutes($start_time, $end_time) {
    	$start_date = new DateTime($start_time);
		$since_start = $start_date->diff(new DateTime($end_time));
		$minutes = $since_start->days * 24 * 60;
		$minutes += $since_start->h * 60;
		$minutes += $since_start->i;
		return $minutes;
    }

}


FacilityBooker $booker = new FacilityBooker($argv);
echo $booker->processBooking();