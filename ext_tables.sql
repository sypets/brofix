# table for broken links
# @deprecated tstamp
CREATE TABLE tx_brofix_broken_links (
	uid int(11) NOT NULL auto_increment,
	tstamp int(11) DEFAULT '0' NOT NULL,
  crdate int(11) DEFAULT '0' NOT NULL,
	record_uid int(11) DEFAULT '0' NOT NULL,
	record_pid int(11) DEFAULT '0' NOT NULL,
	language int(11) DEFAULT '-1' NOT NULL,
	headline varchar(255) DEFAULT '' NOT NULL,
	field varchar(255) DEFAULT '' NOT NULL,
	flexform_field varchar(255) DEFAULT '' NOT NULL,
	flexform_field_label varchar(255) DEFAULT '' NOT NULL,
	table_name varchar(255) DEFAULT '' NOT NULL,
	element_type varchar(255) DEFAULT '' NOT NULL,
	link_title text,
	url text,
	url_hash varchar(40) DEFAULT '' NOT NULL,
	url_response text,
	check_status int(5) DEFAULT '4' NOT NULL,
	last_check int(11) DEFAULT '0' NOT NULL,
	last_check_url int(11) DEFAULT '0' NOT NULL,
	link_type varchar(50) DEFAULT '' NOT NULL,
	exclude_link_targets_pid int(11) DEFAULT '0' NOT NULL,

	KEY url_combined (url_hash,link_type,check_status),
	PRIMARY KEY (uid)
);

# table for link target cache
CREATE TABLE tx_brofix_link_target_cache (
	uid int(11) NOT NULL auto_increment,
	link_type varchar(50) DEFAULT 'external' NOT NULL,
	url text,
	last_check int(11) unsigned DEFAULT '0' NOT NULL,
	check_status int(11) unsigned DEFAULT '0' NOT NULL,
	url_response text,

	PRIMARY KEY (uid)
);

# table for excluding link targets
CREATE TABLE tx_brofix_exclude_link_target (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) DEFAULT '0' NOT NULL,
    crdate int(11) DEFAULT '0' NOT NULL,
    cruser_id int(11) DEFAULT '0' NOT NULL,
    editlock tinyint(4) DEFAULT '0' NOT NULL,
    deleted tinyint(4) DEFAULT '0' NOT NULL,
    hidden tinyint(4) DEFAULT '0' NOT NULL,
    linktarget text DEFAULT '' NOT NULL,
    link_type varchar(20) DEFAULT '' NOT NULL,
    match varchar(20) DEFAULT '' NOT NULL,
    reason int(4) DEFAULT '0' NOT NULL,
    notes varchar(80) DEFAULT '' NOT NULL,

    PRIMARY KEY (uid)
);
