--
--	Extension:CreatedPagesList - MediaWiki extension.
--	Copyright (C) 2018-2021 Edward Chernenko.
--
--	This program is free software; you can redistribute it and/or modify
--	it under the terms of the GNU General Public License as published by
--	the Free Software Foundation; either version 2 of the License, or
--	(at your option) any later version.
--
--	This program is distributed in the hope that it will be useful,
--	but WITHOUT ANY WARRANTY; without even the implied warranty of
--	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
--	GNU General Public License for more details.
--

-- List of pages created by user.
CREATE TABLE /*_*/createdpageslist (
	cpl_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
	cpl_timestamp varbinary(14) NOT NULL DEFAULT '',

	cpl_actor bigint unsigned NOT NULL default 0,
	cpl_page int unsigned NOT NULL

) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/createdpageslist_page ON /*_*/createdpageslist (cpl_page);

-- Index used by Special:CreatedPageList
CREATE INDEX /*i*/createdpageslist_actor_timestamp ON /*_*/createdpageslist (cpl_actor, cpl_timestamp);
