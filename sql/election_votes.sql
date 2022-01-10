BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/election_votes (
	-- Primary key
	vote_id integer unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
	-- Election ID configured in LocalSettings
	vote_election_id VARCHAR(255) NOT NULL,
	-- MediaWiki user ID of voter
	vote_voter_id integer NOT NULL,
	-- Index of candidate in LocalSettings candidates array
	vote_candidate_id integer unsigned NOT NULL,
	-- Rank of candidate for this vote
	vote_candidate_rank integer unsigned NOT NULL
)/*$wgDBTableOptions*/;

CREATE INDEX IF NOT EXISTS /*i*/vote_election_id ON /*_*/election_votes (vote_election_id);
