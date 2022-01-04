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
	
	function __destruct() {
		
	}
	
	function hasUserVoted(User $user) : bool {		
		return $this->db->select('election_votes', ['1'], ['vote_election_id' => $this->electionId, 'vote_voter_id' => $user->getId()], __METHOD__)->numRows() > 0;
	}
	
	function getResults(array $candidates) : array {
		global $wgElectionCandidates;
		
		$numCandidates = sizeof($candidates);
		
		$result = $this->db->select('election_votes', ['candidateId' => 'vote_candidate_id', 'score' => $numCandidates . '*COUNT(vote_candidate_rank)-SUM(vote_candidate_rank)+1'], ['vote_election_id' => $this->electionId], __METHOD__, ['ORDER BY points DESC', 'GROUP BY' => 'vote_candidate_id']);
		
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
		
		$numCandidates = sizeof($wgElectionCandidates);
		
		// Duplicate ranks or missing ranks
		if (sizeof(array_unique($votes)) < $numCandidates) {
			return 'Candidate ranks must be unique, and you must select the rank for each candidate';
		}
		
		foreach ($votes as $candidateId => $score) {
			// Tried to vote for non-candidate
			if (!array_key_exists($candidateId, $wgElectionCandidates)) {
				return 'Illegal voting';
			}
			$vote = $votes[$candidateId];
			$rank = intval($vote);
			// Rank is not the canonical representation of an integer
			if (!ctype_digit($vote) || strval($rank) !== $vote) {
				return 'Illegal voting';
			}
			// Rank is outside the range
			if ($rank < 1 || $rank > $numCandidates) {
				return 'Illegal voting';
			}
		}
		
		return null;
	}
	
	function addVotes(User $user, array $votes) : ?string {
		global $wgElectionMinRegistrationDate;
		
		$this->db->startAtomic(__METHOD__);
		
		try {
			$validationError = $this->validateVotes($votes);
			if ($validationError) {
				return $validationError;
			}
			
			if ($user->getBlock()) {
				return 'You are blocked.';
			}
			
			if (wfTimestamp(TS_UNIX, $user->getRegistration()) < $wgElectionMinRegistrationDate) {
				return 'Your account was created too recently.';
			}
					
			$this->db->insert('election_voters', ['voter_election_id' => $this->electionId, 'voter_voter_id' => $user->getId()], __METHOD__, ['IGNORE']);
			if (!$this->db->insertID()) {
				return 'You have already voted';
			}
			
			$this->db->insert('election_votes', array_map(function ($candidateId, $rank) use($user) {
				return ['vote_election_id' => $this->electionId, 'vote_voter_id' => $user->getId(), 'vote_candidate_id' => $candidateId, 'vote_candidate_rank' => $rank];
			}, array_keys($votes), array_values($votes)), __METHOD__);
		} finally {
			$this->db->endAtomic(__METHOD__);
		}
		return null;
	}
}