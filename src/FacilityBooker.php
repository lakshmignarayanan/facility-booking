<?php

require_once('Utils.php');

Class FacilityBooker
{
	protected $filePath = __DIR__ . "/bookings.csv";
	protected $fileHandle = null;
	protected $facilityId = 0;
	protected $startDateTime, $endDateTime;

	// facility_id <=> name
	private $facilities = [
		'1' => 'Club House',
		'2' => 'Tennis Court',
		'3' => 'Meeting Hall',
		'4' => 'Gym'
	];

	// facility_id <=> rate_type
	private $facilityRateType = [
		'1' => 'variable',
		'2' => 'fixed',
		'3' => 'variable',
		'4' => 'variable'
	];

	// slot_id <=> slot_duration
	private $slots = [
		'0' => '00:00:00_to_24:00:00',
		'1' => '10:00:00_to_16:00:00',
		'2' => '16:00:00_to_22:00:00',
		'3' => '00:00:00_to_24:00:00',
		'4' => '09:00:00_to_17:00:00',
		'5' => '06:00:00_to_09:00:00',
		'6' => '17:00:00_to_21:00:00'
	];

	//slot_id <=> rate_amount
	private $ratesPerHour = [
		'0' => 50,
		'1' => 100, 
		'2' => 500,
		'3' => 50,
		'4' => 70,
		'5' => 1000,
		'6' => 33
	];

	// facility_id <=> slot_id(s) #one-to-many relation
	private $facilitySlots = [
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
		$this->fileHandle = fopen($this->filePath, 'r');
		$this->sanitizeInput($args);
	}

	// returns facility Id as integer
	public function getFacilityId($facility_name = '') {
		if (empty($facility_name)) {
			return 0;
		}
		foreach ($this->facilities as $key => $value) {
			if ($facility_name === $value) {
				return intval($key);
			}
		}
		return 0;
	}

	public function sanitizeInput($input = '') {
		if (count($input) < 2) {
			exit("Empty input values\n");
		}
		$values = explode(",", $input[1]);
		$values[1] = Utils::removeWhitespaces($values[1]);
		$values[2] = Utils::removeWhitespaces($values[2]);
		$values[3] = Utils::removeWhitespaces($values[3]);
		if (count($values) != 4 || empty($values[1]) || empty($values[2]) || empty($values[3])) {
			exit("Please check your input details\n");
		} elseif ($this->getFacilityId($values[0]) == 0) {
			exit("Facility " . $values[0] . "doesn't exist\n");
		} elseif (!Utils::validateDateString($values[1] . ' ' . $values[2] . ':00')) {
			exit("Invalid start time\n");
		} elseif (!Utils::validateDateString($values[1] . ' ' . $values[3] . ':00')) {
			exit("Invalid end time\n");
		}
		$this->facilityId = $this->getFacilityId($values[0]);
		$this->startDateTime = Utils::formatDateTime($values[1] . ' ' . $values[2] . ':00');
		$this->endDateTime = Utils::formatDateTime($values[1] . ' ' . $values[3] . ':00');
	}

	public function processBooking() {
		// check if facility is available
		if (!$this->isFacilityAvailable($this->facilityId, $this->startDateTime, $this->endDateTime)) {
			fclose($this->fileHandle);
			$this->fileHandle = null;
			exit("Booking failed, already booked!\n");
		} else {
			// lets do the booking
			$startTime = Utils::formatDateTime($this->startDateTime, 'H:i:s');
			$endTime = Utils::formatDateTime($this->endDateTime, 'H:i:s');
			$rate = $this->calculateRate($this->facilityId, $startTime, $endTime);
			if ($rate > 0) {
				$appendHandle = fopen($this->filePath, "a");
				$data = "\n" . $this->facilityId . "," . $this->startDateTime . "," . $this->endDateTime;
				fwrite($appendHandle, $data);
				fclose($appendHandle);
				return "Booked, Rs." . $rate . "\n";	
			} else {
				return "Booking failed, try someother options\n";
			}
			
		}
	}

	public function isFacilityAvailable($facility_id, $start_time, $end_time) {
		
		if (!is_null($this->fileHandle)) {
			$available = true;
			$facilityHasBookings = false;

			while (($bookings = fgetcsv($this->fileHandle)) !== false) {
				$bookedFacility = intval($bookings[0]);
				$bookedStartTime = $bookings[1];
				$bookedEndTime = $bookings[2];
				if ($bookedFacility == $facility_id) {
					$facilityHasBookings = true;
					if ((strtotime($start_time) >= strtotime($bookedStartTime) && strtotime($end_time) <= strtotime($bookedEndTime)) || (strtotime($start_time) >= strtotime($bookedStartTime) && strtotime($start_time) < strtotime($bookedEndTime)) || (strtotime($end_time) > strtotime($bookedStartTime) && strtotime($end_time) <= strtotime($bookedEndTime))) {
						$available = false;
						break;
					}
				}
			}
		
			if (!$facilityHasBookings || $available) {
				return true;
			}
			return false;
		
		} else {
			exit("Error handling previous booking details\n");
		}
	}

	// returns rate
	public function calculateRate($facility_id, $start_time, $end_time) {
		// check if it's variable rate
		if ($this->facilityRateType[$facility_id] === 'fixed') {
			$hoursToBook = Utils::getTimeDifferenceInHours($start_time, $end_time);
			return $this->ratesPerHour[0] * $hoursToBook;
		} else {
			// get the slots
			$slotsApplicable = $this->facilitySlots[$facility_id];
			$shortlistedSlots = [];
			foreach ($slotsApplicable as $slotid) {
				$shortlistedSlots[$slotid] = $this->slots[$slotid];
			}
			foreach ($shortlistedSlots as $slotId => $slotTimings) {
				$slotStart = explode("_to_", $slotTimings)[0];
				$slotEnd = explode("_to_", $slotTimings)[1];
				if ($start_time >= $slotStart && $end_time <= $slotEnd) {
					// falls within a single block
					$ratePerHr = $this->ratesPerHour[$slotId];
					return $ratePerHr * Utils::getTimeDifferenceInHours($start_time, $end_time);
				} elseif ($start_time > $slotStart && $start_time < $slotEnd && $end_time > $slotEnd) {
					// booking time stretches rightwards out of the slots
					// calc the 1st part
					$intialHrs = Utils::getTimeDifferenceInHours($start_time, $slotEnd);
					$ratePerHr = $this->ratesPerHour[$slotId];
					$totalRate = $intialHrs * $ratePerHr;
					foreach ($shortlistedSlots as $slotId2 => $slotTimings2) {
						$slotStart2 = explode("_to_", $slotTimings2)[0];
						$slotEnd2 = explode("_to_", $slotTimings2)[1];
						if ($start_time < $slotStart2 && $end_time > $slotStart2 && $end_time < $slotEnd2) {
							// calc the remaining part of the duration that stretches leftwards of the prev slot
							$laterHrs = Utils::getTimeDifferenceInHours($slotStart2, $end_time);
							$totalRate = $totalRate + ($laterHrs * $this->ratesPerHour[$slotId2]);
							return $totalRate;
						}
					}
				} elseif ($start_time < $slotStart && $end_time > $slotStart && $end_time < $slotEnd) {
					// slot stretches leftwards first
					$laterHrs = Utils::getTimeDifferenceInHours($slotStart, $end_time);//calc rightmost part
					$ratePerHr = $this->ratesPerHour[$slotId];
					$totalRate = $laterHrs * $ratePerHr;
					foreach ($shortlistedSlots as $slotId2 => $slotTimings2) {
						$slotStart2 = explode("_to_", $slotTimings2)[0];
						$slotEnd2 = explode("_to_", $slotTimings2)[1];
						if ($start_time < $slotEnd2 && $start_time > $slotStart2 && $end_time > $slotEnd2) {
							// calc the leftmost part
							$initHrs = Utils::getTimeDifferenceInHours($start_time, $slotEnd2);
							$ratePerHr = $this->ratesPerHour[$slotId];
							$totalRate = $totalRate + ($initHrs * $ratePerHr);
							return $totalRate;
						}
					}
				} else {
					//lies in an unknown block
					return 0;
				}
			}

		}
	}

}

$booker = new FacilityBooker($argv);
echo $booker->processBooking();