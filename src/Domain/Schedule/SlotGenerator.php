<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Schedule;

use DateTimeImmutable;

/**
 * Pure time-slot generator.
 *
 * Given a day's open/close window, a list of breaks, and already-booked blocks,
 * produces an ordered list of available TimeSlot value objects.
 *
 * This class has no external dependencies and is trivially unit-testable.
 */
final class SlotGenerator {

	/**
	 * Generates available time slots for a single day.
	 *
	 * @param DateTimeImmutable                                             $date
	 * @param string                                                        $openTime      "HH:MM"
	 * @param string                                                        $closeTime     "HH:MM"
	 * @param int                                                           $slotDuration  Minutes per slot
	 * @param int                                                           $slotInterval  Minutes between slot starts
	 * @param int                                                           $bufferBefore  Minutes blocked before each appointment
	 * @param int                                                           $bufferAfter   Minutes blocked after each appointment
	 * @param int                                                           $minimumNotice Minutes from now required to book
	 * @param list<array{start: string, end: string}>                       $breaks        [{start:"HH:MM", end:"HH:MM"}]
	 * @param list<array{start: DateTimeImmutable, end: DateTimeImmutable}> $bookedBlocks
	 * @return list<TimeSlot>
	 */
	public function generate(
		DateTimeImmutable $date,
		string $openTime,
		string $closeTime,
		int $slotDuration,
		int $slotInterval,
		int $bufferBefore,
		int $bufferAfter,
		int $minimumNotice,
		array $breaks,
		array $bookedBlocks,
	): array {
		$dateStr          = $date->format( 'Y-m-d' );
		$now              = new DateTimeImmutable();
		$earliestBookable = $now->modify( "+{$minimumNotice} minutes" );

		// Convert open/close to timestamps.
		$dayOpen  = new DateTimeImmutable( "{$dateStr} {$openTime}" );
		$dayClose = new DateTimeImmutable( "{$dateStr} {$closeTime}" );

		if ( $dayOpen >= $dayClose ) {
			return array();
		}

		// Pre-compute break blocks as [startTs, endTs] pairs.
		$breakBlocks = array_map(
			fn( array $b ) => array(
				( new DateTimeImmutable( "{$dateStr} {$b['start']}" ) )->getTimestamp(),
				( new DateTimeImmutable( "{$dateStr} {$b['end']}" ) )->getTimestamp(),
			),
			$breaks
		);

		// Pre-compute booked blocks with buffers as [startTs, endTs] pairs.
		$occupiedBlocks = array_map(
			fn( array $b ) => array(
				$b['start']->modify( "-{$bufferBefore} minutes" )->getTimestamp(),
				$b['end']->modify( "+{$bufferAfter} minutes" )->getTimestamp(),
			),
			$bookedBlocks
		);

		$slots       = array();
		$currentTime = $dayOpen;
		$closeTs     = $dayClose->getTimestamp();

		while ( true ) {
			$slotStartTs = $currentTime->getTimestamp();
			$slotEndTs   = $slotStartTs + ( $slotDuration * 60 );

			// Stop when slot end would exceed day close.
			if ( $slotEndTs > $closeTs ) {
				break;
			}

			if ( $this->isAvailable(
				$slotStartTs,
				$slotEndTs,
				$breakBlocks,
				$occupiedBlocks,
				$earliestBookable->getTimestamp()
			) ) {
				$slots[] = new TimeSlot(
					time:            $currentTime->format( 'H:i' ),
					datetime:        $currentTime,
					durationMinutes: $slotDuration,
					available:       true,
				);
			}

			// Advance by interval.
			$currentTime = $currentTime->modify( "+{$slotInterval} minutes" );
		}

		return $slots;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Checks whether a slot window [startTs, endTs] is free of conflicts.
	 *
	 * @param list<array{int, int}> $breakBlocks
	 * @param list<array{int, int}> $occupiedBlocks
	 */
	private function isAvailable(
		int $startTs,
		int $endTs,
		array $breakBlocks,
		array $occupiedBlocks,
		int $earliestBookableTs
	): bool {
		// Must be bookable in time (minimum notice).
		if ( $startTs < $earliestBookableTs ) {
			return false;
		}

		// Must not overlap a break.
		foreach ( $breakBlocks as [$bStart, $bEnd] ) {
			if ( $startTs < $bEnd && $endTs > $bStart ) {
				return false;
			}
		}

		// Must not overlap a booked block (including buffers).
		foreach ( $occupiedBlocks as [$bStart, $bEnd] ) {
			if ( $startTs < $bEnd && $endTs > $bStart ) {
				return false;
			}
		}

		return true;
	}
}
