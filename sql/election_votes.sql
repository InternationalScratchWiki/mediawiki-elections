BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/election_votes (
	-- Primary key
	vote_id integer unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
	vote_election_id VARCHAR(255) NOT NULL,
	vote_voter_id integer NOT NULL,
	vote_candidate_id integer unsigned NOT NULL,
	vote_candidate_rank integer unsigned NOT NULL
)/*$wgDBTableOptions*/;

CREATE INDEX IF NOT EXISTS /*i*/vote_election_id ON /*_*/election_votes (vote_election_id);
