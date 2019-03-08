<?php

require_once('Utils.php');

Class FacilityBooker
{
	protected $filePath = "bookings.csv";
	protected $fileHandle = null;
	protected $facilityId = 0;
	protected $startDateTime, $endDateTime;

	private $facilities = [
		'1' => 'Club House',
		'2' => 'Tennis Court',
		'3' => 'Meeting Hall',
		'4' => 'Gym'
	];

	// facility_id <=> ratetype
	private $facilityRateType = [
		'1' => 'variable',
		'2' => 'fixed',
		'3' => 'variable',
		'4' => 'variable'
	];

	// slotid <=> duration
	private $slots = [
		'0' => '00:00:00_to_24:00:00',
		'1' => '10:00:00_to_16:00:00',
		'2' => '16:00:00_to_22:00:00',
		'3' => '16:00:00_to_22:00:00',
		'4' => '09:00:00_to_17:00:00',
		'5' => '06:00:00_to_09:00:00',
		'6' => '17:00:00_to_21:00:00'
	];

	//slotId <=> rateAmount
	private $ratesPerHour = [
		'0' => 50,
		'1' => 100, 
		'2' => 500,
		'3' => 20,
		'4' => 70,
		'5' => 1000,
		'6' => 33
	];

	// facilityId <=> slot IDs
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
			exit("Empty input values");
		}
		// error_log("input : " . json_encode($input));
		$values = explode(",", $input[1]);
		if (count($values) != 4 || empty($values[1]) || empty($values[2]) || empty($values[3])) {
			// error_log("hi values");
			// print_r($values);
			exit("Please check your input details");
		} elseif ($this->getFacilityId($values[0]) == 0) {
			exit("Facility " . $values[0] . "doesn't exist");
		} elseif (!Utils::validateDateString($values[1] . ' ' . $values[2] . ':00')) {
			exit("Invalid start time");
		} elseif (!Utils::validateDateString($values[1] . ' ' . $values[3] . ':00')) {
			exit("Invalid end time");
		}
		$this->facilityId = $this->getFacilityId($values[0]);
		$this->startDateTime = Utils::formatDateTime($values[1] . ' ' . $values[2] . ':00');
		$this->endDateTime = Utils::formatDateTime($values[1] . ' ' . $values[3] . ':00');
	}

	public function processBooking() {
		// check facility avl
		if (!$this->isFacilityAvailable($this->facilityId, $this->startDateTime, $this->endDateTime)) {
			exit("Sorry, the facility is already booked!");
		} else {
			// book
			$startTime = Utils::formatDateTime($this->startDateTime, 'H:i:s');
			$endTime = Utils::formatDateTime($this->endDateTime, 'H:i:s');
			$rate = $this->calculateRate($this->facilityId, $startTime, $endTime);
			if ($rate > 0) {
				$appendHandle = fopen($this->filePath, "a");
				$data = "\n" . $this->facilityId . "," . $this->startDateTime . "," . $this->endDateTime;
				fwrite($appendHandle, $data);
				return "Booked, Rs." . $rate;	
			} else {
				return "Booking failed, try someother options";
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
				error_log("\nbookedFacility=".$bookedFacility." bookedStartTime=".$bookedStartTime." bookedEndTime=".$bookedEndTime);
				error_log("facility_id=".$facility_id." start_time=".$start_time." end_time=".$end_time);
				if ($bookedFacility == $facility_id) {
					error_log($bookedFacility . " == " . $facility_id);
					$facilityHasBookings = true;
					if ($start_time > $bookedStartTime && $start_time < $bookedEndTime) {
						error_log($bookedStartTime . " < " . $start_time . " < " . $bookedEndTime);
					}
					if ((strtotime($start_time) >= strtotime($bookedStartTime) && strtotime($end_time) <= strtotime($bookedEndTime)) || (strtotime($start_time) >= strtotime($bookedStartTime) && strtotime($start_time) < strtotime($bookedEndTime)) || (strtotime($end_time) > strtotime($bookedStartTime) && strtotime($end_time) <= strtotime($bookedEndTime))) {
						error_log("\nslot booked..");
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
			exit("Error handling previous booking details");
		}
	}

	// returns rate
	public function calculateRate($facility_id, $start_time, $end_time) {
		// check if variable rate
		if ($this->facilityRateType[$facility_id] === 'fixed') {
			$hoursToBook = Utils::getTimeDifferenceInHours($start_time, $end_time);
			return ceil($this->ratesPerHour[0] * $hoursToBook);
		} else {
			// get the slots
			$slotsApplicable = $this->facilitySlots[$facility_id];
			$shortlistedSlots = [];
			// $totalRate = 0;
			error_log("slotsApplicable = " . json_encode($slotsApplicable));
			foreach ($slotsApplicable as $slotid) {
				$shortlistedSlots[$slotid] = $this->slots[$slotid];
			}
			error_log("shortlistedSlots=".json_encode($shortlistedSlots));
			foreach ($shortlistedSlots as $slotId => $slotTimings) {
				$slotStart = explode("_to_", $slotTimings)[0];
				$slotEnd = explode("_to_", $slotTimings)[1];
				error_log("slotStart=".$slotStart."/slotEnd=".$slotEnd."/start_time=".$start_time."/end_time=".$end_time);
				if ($start_time >= $slotStart && $end_time <= $slotEnd) {
					error_log("falls in block");
					// falls within slot block
					$ratePerHr = $this->ratesPerHour[$slotId];
					return ceil($ratePerHr * Utils::getTimeDifferenceInHours($start_time, $end_time));
				} elseif ($start_time > $slotStart && $start_time < $slotEnd && $end_time > $slotEnd) {
					error_log("stretches rightwards..");
					// booking time stretches rightwards out of the slots
					// calc 1st part
					$intialHrs = Utils::getTimeDifferenceInHours($start_time, $slotEnd);
					error_log("intialHrs = " . $intialHrs);
					$ratePerHr = $this->ratesPerHour[$slotId];
					error_log("ratePerHr=" . $ratePerHr . " for slotid" . $slotId);
					$totalRate = $intialHrs * $ratePerHr;
					foreach ($shortlistedSlots as $slotId2 => $slotTimings2) {
						$slotStart2 = explode("_to_", $slotTimings2)[0];
						$slotEnd2 = explode("_to_", $slotTimings2)[1];
						if ($start_time < $slotStart2 && $end_time > $slotStart2 && $end_time < $slotEnd2) {
							error_log("slotStart2=".$slotStart2."/slotEnd2=".$slotEnd2."/start_time=".$start_time."/end_time=".$end_time);
							// calc the remaining part of the duration that stretches leftward
							$laterHrs = Utils::getTimeDifferenceInHours($slotStart2, $end_time);
							error_log("laterHrs=".$laterHrs);
							error_log("ratePerHr = " . $this->ratesPerHour[$slotId2] . " for slot " . $slotId2);
							error_log("previous rate = " . $totalRate);
							error_log("additional rate= " . $laterHrs * $this->ratesPerHour[$slotId2]);
							$totalRate = $totalRate + ($laterHrs * $this->ratesPerHour[$slotId2]);
							return $totalRate;
						}
					}
				} elseif ($start_time < $slotStart && $end_time > $slotStart && $end_time < $slotEnd) {
					error_log("stretches leftwards..");
					// slot stretches leftwards first
					$laterHrs = Utils::getTimeDifferenceInHours($slotStart, $end_time);//calc rightmost part
					error_log("laterHrs=".$laterHrs);
					$ratePerHr = $this->ratesPerHour[$slotId];
					error_log("ratePerHr=".$ratePerHr." for slotId" . $slotId);
					$totalRate = $laterHrs * $ratePerHr;
					foreach ($shortlistedSlots as $slotId2 => $slotTimings2) {
						$slotStart2 = explode("_to_", $slotTimings2)[0];
						$slotEnd2 = explode("_to_", $slotTimings2)[1];
						if ($start_time < $slotEnd2 && $start_time > $slotStart2 && $end_time > $slotEnd2) {
							// calc the leftmost part
							error_log("slotStart2=".$slotStart2."/slotEnd2=".$slotEnd2."/start_time=".$start_time."/end_time=".$end_time);
							$initHrs = Utils::getTimeDifferenceInHours($start_time, $slotEnd2);
							$ratePerHr = $this->ratesPerHour[$slotId];
							$totalRate = $totalRate + ($initHrs * $ratePerHr);
							return $totalRate;
						}
					}
				} else {
					error_log("lies in an unknown block");
					return 0;
				}
			}

		}
	}

}

$booker = new FacilityBooker($argv);
echo $booker->processBooking();