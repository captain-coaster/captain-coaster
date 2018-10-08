CREATE TABLE coaster_launch (coaster_id INT NOT NULL, launch_id INT NOT NULL, INDEX IDX_C678D027216303C (coaster_id), INDEX IDX_C678D02775B199CE (launch_id), PRIMARY KEY(coaster_id, launch_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB;
CREATE TABLE coaster_type (coaster_id INT NOT NULL, type_id INT NOT NULL, INDEX IDX_B0990AFA216303C (coaster_id), INDEX IDX_B0990AFAC54C8C93 (type_id), PRIMARY KEY(coaster_id, type_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB;
ALTER TABLE coaster ADD restraint_id INT NOT NULL, ADD speed INT DEFAULT NULL, ADD height INT DEFAULT NULL, ADD length INT DEFAULT NULL, ADD inversions_number INT DEFAULT NULL, ADD is_kiddie TINYINT(1) NOT NULL, ADD manufacturer_id INT NOT NULL, DROP notes;

#####################################################################################

update coaster c
join built_coaster bc on bc.id = c.built_coaster_id
join manufacturer m on m.id = bc.manufacturer_id
join restraint r on r.id = bc.restraint_id
set c.speed = bc.speed,
    c.length = bc.length,
    c.height = bc.height,
    c.inversions_number = bc.inversionsNumber,
    c.manufacturer_id = bc.manufacturer_id,
    c.restraint_id = bc.restraint_id,
    c.is_kiddie = bc.is_kiddie
;

insert into coaster_launch (coaster_id, launch_id)
select c.id, bcl.launch_id from built_coaster_launch bcl
join built_coaster bc on bcl.built_coaster_id = bc.id
join coaster c on c.built_coaster_id = bc.id;

insert into coaster_type (coaster_id, type_id)
select c.id, bct.type_id from built_coaster_type bct
join built_coaster bc on bct.built_coaster_id = bc.id
join coaster c on c.built_coaster_id = bc.id;


#####################################################################################

ALTER TABLE coaster_launch ADD CONSTRAINT FK_C678D027216303C FOREIGN KEY (coaster_id) REFERENCES coaster (id) ON DELETE CASCADE;
ALTER TABLE coaster_launch ADD CONSTRAINT FK_C678D02775B199CE FOREIGN KEY (launch_id) REFERENCES launch (id) ON DELETE CASCADE;
ALTER TABLE coaster_type ADD CONSTRAINT FK_B0990AFA216303C FOREIGN KEY (coaster_id) REFERENCES coaster (id) ON DELETE CASCADE;
ALTER TABLE coaster_type ADD CONSTRAINT FK_B0990AFAC54C8C93 FOREIGN KEY (type_id) REFERENCES type (id) ON DELETE CASCADE;

ALTER TABLE coaster ADD CONSTRAINT FK_F6312A78A23B42D FOREIGN KEY (manufacturer_id) REFERENCES manufacturer (id);
ALTER TABLE coaster ADD CONSTRAINT FK_F6312A78950622D7 FOREIGN KEY (restraint_id) REFERENCES restraint (id);
CREATE INDEX IDX_F6312A78A23B42D ON coaster (manufacturer_id);
CREATE INDEX IDX_F6312A78950622D7 ON coaster (restraint_id);

ALTER TABLE coaster DROP FOREIGN KEY FK_F6312A78FCD55F79;
ALTER TABLE coaster DROP built_coaster_id;
DROP INDEX IDX_F6312A78FCD55F79 ON coaster;

drop table built_coaster_launch;
drop table built_coaster_type;
drop table built_coaster;







INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (1, 'Sit Down', 'sit-down');


update coaster c
join built_coaster bc on bc.id = c.built_coaster_id
join built_coaster_type bct on bct.built_coaster_id = bc.id
join type t on t.id = bct.type_id
set c.seating_type_id = 1
where t.name like 'Sit Down';

#################

# users and home parks
select p.name, u.username from users u
join park p on p.id = u.home_park_id
where home_park_id is not null
order by p.country_id, p.name;

# manufacturer with rating = 5
select count(1), m.name from ridden_coaster r
  join coaster c on c.id = r.coaster_id
  join built_coaster bc on bc.id = c.built_coaster_id
  join manufacturer m on m.id = bc.manufacturer_id
where r.user_id = 6 and rating = 5
group by m.id;

# manufacturer most often in top with points
select count(1) as nb, sum((1 / lc.position)*10) as nbpoint, m.name from liste l
  join liste_coaster lc on lc.liste_id = l.id
  join coaster c on c.id = lc.coaster_id
  join built_coaster bc on bc.id = c.built_coaster_id
  join manufacturer m on m.id = bc.manufacturer_id
where l.user_id = 6 and position < 11
group by m.id
order by nbpoint desc;

# type most often with rating 5
select count(1) as nb, t.name from ridden_coaster r
  join coaster c on c.id = r.coaster_id
  join built_coaster bc on bc.id = c.built_coaster_id
  join built_coaster_type bct on bct.built_coaster_id = bc.id
  join type t on t.id = bct.type_id
where r.user_id = 25 and rating = 5
group by t.id
order by nb desc;

# type most often in top with points
select count(1) as nb, sum((1 / lc.position)*10) as nbpoint, t.name from liste l
  join liste_coaster lc on lc.liste_id = l.id
  join coaster c on c.id = lc.coaster_id
  join built_coaster bc on bc.id = c.built_coaster_id
  join built_coaster_type bct on bct.built_coaster_id = bc.id
  join type t on t.id = bct.type_id
where l.user_id = 25 and position < 11
group by t.id
order by nbpoint desc;

# most ridden type...
select count(1) as nb, t.name from ridden_coaster r
  join coaster c on c.id = r.coaster_id
  join built_coaster bc on bc.id = c.built_coaster_id
  join built_coaster_type bct on bct.built_coaster_id = bc.id
  join type t on t.id = bct.type_id
where r.user_id = 6
group by t.id
order by nb desc;

# most pro main tags in top (always airtimes)
select count(1) as nb, sum((1 / lc.position)*10) as nbpoint, t.name from liste l
  join liste_coaster lc on lc.liste_id = l.id
  join coaster c on c.id = lc.coaster_id
  join main_tag mt on mt.coaster_id = c.id
  join tag t on t.id = mt.tag_id
where l.user_id = 25 and position < 11 and t.`type` = 'pro'
group by t.id
order by nbpoint desc;

# most pro main tags in ratings (always airtimes)
select count(1) as nb, t.name from ridden_coaster r
  join coaster c on c.id = r.coaster_id
  join main_tag mt on mt.coaster_id = c.id
  join tag t on t.id = mt.tag_id
where r.user_id = 25 and rating = 5 and t.`type` = 'pro'
group by t.id
order by nb desc;
