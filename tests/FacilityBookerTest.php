<?php

use PHPUnit\Framework\TestCase;

final Class FacilityBookerTest extends TestCase {

	public function testFacilityNotFound(): void
	{
		$this->assertEquals(0, FacilityBooker::getFacilityId('abc'));
	}

	public function testCalculateRateSucess(): void
	{
		$this->assertEquals(1, (FacilityBooker::calculateRate(2, '09:00:00', '12:00:00') > 0)); // returned rate should be greater than 0 to verify if the rate calculation passes when provided with correct inputs
	}

	public function testCalculateRateFailure(): void
	{
		$this->assertEquals(0, (FacilityBooker::calculateRate(2, '09:00:00', '09:00:00') > 0)); // returned rate should be 0 since starttime and endtime are same
	}

	public function testFacilityAvailabilityChecker(): void
	{
		$this->assertEquals(0, FacilityBooker::isFacilityAvailable(1, '2020-10-10 09:00:00','2020-10-10 11:00:00'));
	}

}