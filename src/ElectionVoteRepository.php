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
}

class ElectionVoteRepository extends ElectionVoteLoader {	
	function __construct($method, $electionId) {
		parent::__construct($method, $electionId, wfGetDB(DB_MASTER));
	}
	
	function validateVotes(array $votes) : ?string {
		global $wgElectionCandidates;
		
		$numCandidates = sizeof($wgElectionCandidates);
		if (sizeof(array_unique($votes)) < $numCandidates) {
			// TODO: show an error message
			echo 'Candidate ranks must be unique';
			die;
		}
		
		if (sizeof(
				array_filter(
					array_keys($wgElectionCandidates),
					function ($candidateId) use ($votes, $numCandidates) { 
						if (!array_key_exists($candidateId, $votes))
							return false;
						
						if ($votes[$candidateId] < 1 || $votes[$candidateId] > $numCandidates)
							return false;
						
						return true;
					}
				)
			)			
			!= $numCandidates) {
			return 'Illegal voting';
		}
		
		return null;
	}
	
	function addVotes(User $user, array $votes) : ?string {
		$this->db->startAtomic(__METHOD__);
		
		if ($this->hasUserVoted($user)) {
			return 'You have already voted';;
		}
		
		$validationError = $this->validateVotes($votes);
		if ($validationError) {
			return $validationError;
		}
		
		$this->db->insert('election_votes', array_map(function ($candidateId, $rank) use($user) {
			return ['vote_election_id' => $this->electionId, 'vote_voter_id' => $user->getId(), 'vote_candidate_id' => $candidateId, 'vote_candidate_rank' => $rank];
		}, array_keys($votes), array_values($votes)), __METHOD__);
		
		$this->db->endAtomic(__METHOD__);
		
		return null;
	}
}