<?php
class ElectionVoteLoader {
	protected $db;
	protected $electionId;

	function __construct(string $method, string $electionId, IDatabase $db) {
		$this->db = $db;

		$this->electionId = $electionId;
	}

	function hasUserVoted(User $user) : bool {
		return $this->db->selectRowCount('election_votes', ['1'], [
			'vote_election_id' => $this->electionId,
			'vote_voter_id' => $user->getId()
		], __METHOD__) > 0;
	}

	function getResults(array $candidates) : array {
		global $wgElectionCountMethod;

		$numCandidates = count($candidates);

		$results = [];
		switch ($wgElectionCountMethod) {
			case 'confidence':
				$cols = [
					'candidateId' => 'vote_candidate_id',
					'score' => 'SUM(vote_candidate_rank)'
				];
				break;
			case 'borda':
			default:
				$cols = [
					'candidateId' => 'vote_candidate_id',
					'score' => $numCandidates . '*COUNT(vote_candidate_rank)-SUM(vote_candidate_rank)+1'
				];
		}
		$result = $this->db->select(
			'election_votes', $cols,
			['vote_election_id' => $this->electionId], __METHOD__,
			['ORDER BY score DESC', 'GROUP BY' => 'vote_candidate_id']
		);
		foreach ($result as $row) {
			$results[$candidates[$row->candidateId]] = $row->score;
		}
		$voteCounts = [];
		$result = $this->db->select(
			'election_votes',
			[
				'candidateId' => 'vote_candidate_id',
				'numVotes' => 'COUNT(vote_candidate_rank)'
			],
			['vote_election_id' => $this->electionId], __METHOD__,
			['GROUP BY' => 'vote_candidate_id']
		);
		foreach ($result as $row) {
			$voteCounts[$candidates[$row->candidateId]] = $row->numVotes;
		}
		return ['results' => $results, 'voteCounts' => $voteCounts];
	}
}

class ElectionVoteRepository extends ElectionVoteLoader {
	function __construct($method, $electionId, IDatabase $db) {
		parent::__construct($method, $electionId, $db);
	}
	
	static function getEligibilityError(User $user) : ?string {
		global $wgElectionActive, $wgElectionMinRegistrationDate;
		
		if (!$wgElectionActive) {
			return 'inactive';
		}

		if ($user->getBlock()) {
			return 'blocked';
		}

		if ($wgElectionMinRegistrationDate && wfTimestamp(TS_UNIX, $user->getRegistration()) > $wgElectionMinRegistrationDate) {
			return 'age';
		}
		
		return null;
	}

	function validateVotes(array $votes) : ?string {
		global $wgElectionCandidates, $wgElectionCountMethod;

		$numCandidates = count($wgElectionCandidates);

		// Duplicate ranks or missing ranks
		if (
			$wgElectionCountMethod === 'borda'
			&& count(array_unique($votes)) < count($votes)
		) {
			return 'duplicate-missing';
		}

		foreach ($votes as $candidateId => $score) {
			// Tried to vote for non-candidate
			if (!array_key_exists($candidateId, $wgElectionCandidates)) {
				return 'illegal-voting';
			}
			$vote = $votes[$candidateId];
			$rank = intval($vote);
			// Rank is not the canonical representation of an integer
			if (!ctype_digit($vote) || strval($rank) !== $vote) {
				return 'illegal-voting';
			}
			switch ($wgElectionCountMethod) {
				case 'confidence':
					// Not yes/no vote
					if ($rank !== 0 && $rank !== 1) {
						return 'illegal-voting';
					}
					break;
				case 'borda':
				default:
					// Rank is outside the range
					if ($rank < 1 || $rank > $numCandidates) {
						return 'illegal-voting';
					}
			}
		}

		return null;
	}

	function addVotes(User $user, array $votes) : ?string {
		$this->db->startAtomic(__METHOD__);

		try {
			$validationError = $this->validateVotes($votes);
			if ($validationError) {
				return $validationError;
			}
			
			$validationError = self::getEligibilityError($user);
			if ($validationError) {
				return $validationError;
			}

			$this->db->insert('election_voters', [
				'voter_election_id' => $this->electionId,
				'voter_voter_id' => $user->getId()
			], __METHOD__, ['IGNORE']);
			if (!$this->db->insertID()) {
				return 'voted';
			}

			$this->db->insert('election_votes', array_map(function ($candidateId, $rank) use($user) {
				return [
					'vote_election_id' => $this->electionId,
					'vote_voter_id' => $user->getId(),
					'vote_candidate_id' => $candidateId,
					'vote_candidate_rank' => $rank
				];
			}, array_keys($votes), array_values($votes)), __METHOD__);
		} finally {
			$this->db->endAtomic(__METHOD__);
		}
		return null;
	}
}