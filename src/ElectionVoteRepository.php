<?php
class ElectionVoteLoader {
	protected $db;
	protected $electionId;

	function __construct(string $method, string $electionId, ?IDatabase $db = null) {
		if ($db === null) {
			$this->db = wfGetDB(DB_REPLICA);
		} else {
			$this->db = $db;
		}

		$this->electionId = $electionId;
	}

	function hasUserVoted(User $user) : bool {
		return $this->db->selectRowCount('election_votes', ['1'], [
			'vote_election_id' => $this->electionId,
			'vote_voter_id' => $user->getId()
		], __METHOD__) > 0;
	}

	function getResults(array $candidates) : array {
		global $wgElectionCandidates;

		$numCandidates = count($candidates);

		$result = $this->db->select('election_votes', [
			'candidateId' => 'vote_candidate_id',
			'score' => $numCandidates . '*COUNT(vote_candidate_rank)-SUM(vote_candidate_rank)+1'
		], ['vote_election_id' => $this->electionId], __METHOD__, [
			'ORDER BY score DESC', 'GROUP BY' => 'vote_candidate_id'
		]);

		$results = [];
		foreach ($result as $row) {
			$results[$candidates[$row->candidateId]] = $row->score;
		}
		return $results;
	}
}

class ElectionVoteRepository extends ElectionVoteLoader {
	function __construct($method, $electionId) {
		parent::__construct($method, $electionId, wfGetDB(DB_MASTER));
	}

	function validateVotes(array $votes) : ?string {
		global $wgElectionCandidates;

		$numCandidates = count($wgElectionCandidates);

		// Duplicate ranks or missing ranks
		if (count(array_unique($votes)) < $numCandidates) {
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
			// Rank is outside the range
			if ($rank < 1 || $rank > $numCandidates) {
				return 'illegal-voting';
			}
		}

		return null;
	}

	function addVotes(User $user, array $votes) : ?string {
		global $wgElectionActive, $wgElectionMinRegistrationDate;

		$this->db->startAtomic(__METHOD__);

		try {
			$validationError = $this->validateVotes($votes);
			if ($validationError) {
				return $validationError;
			}

			if (!$wgElectionActive) {
				return 'inactive';
			}

			if ($user->getBlock()) {
				return 'blocked';
			}

			if (wfTimestamp(TS_UNIX, $user->getRegistration()) < $wgElectionMinRegistrationDate) {
				return 'age';
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