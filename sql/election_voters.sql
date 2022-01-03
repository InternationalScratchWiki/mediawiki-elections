BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/election_voters (
	-- Primary key
	voter_id integer unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
	voter_election_id VARCHAR(255) NOT NULL,
	voter_voter_id integer NOT NULL,
	CONSTRAINT /*i*/voter_election_unique UNIQUE(voter_voter_id, voter_election_id)
)/*$wgDBTableOptions*/;
