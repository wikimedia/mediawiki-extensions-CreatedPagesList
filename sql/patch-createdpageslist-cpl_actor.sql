-- Since CreatedPagesList 1.2.0: add cpl_actor field, remove cpl_user_text and cpl_user fields.

ALTER TABLE /*_*/createdpageslist
	ADD COLUMN cpl_actor bigint unsigned NOT NULL default 0,
	DROP COLUMN cpl_user,
	DROP COLUMN cpl_user_text;

-- Index used by Special:CreatedPageList
CREATE INDEX /*i*/createdpageslist_actor_timestamp ON /*_*/createdpageslist (cpl_actor, cpl_timestamp);

-- Remove the old index.
DROP INDEX /*i*/createdpageslist_user_timestamp ON /*_*/createdpageslist;
