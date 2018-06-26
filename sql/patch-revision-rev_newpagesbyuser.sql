-- Add INDEX for Special:CreatedPagesList.

CREATE INDEX /*i*/rev_newpagesbyuser ON /*_*/revision (rev_user_text, rev_parent_id, rev_timestamp);
