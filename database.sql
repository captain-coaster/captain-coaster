INSERT INTO `continent` (`name`, `slug`) VALUES ('continent.europe', 'europe');
INSERT INTO `continent` (`name`, `slug`) VALUES ('continent.america', 'america');
INSERT INTO `continent` (`name`, `slug`) VALUES ('continent.asia', 'Asia');
INSERT INTO `continent` (`name`, `slug`) VALUES ('continent.africa', 'Africa');
INSERT INTO `continent` (`name`, `slug`) VALUES ('continent.oceania', 'oceania');

ALTER TABLE coaster ADD average_top_rank NUMERIC(4, 1) DEFAULT NULL, ADD total_tops_in INT NOT NULL, CHANGE total_ratings total_ratings INT NOT NULL;

# user number
select count(1) from users;

# rating number
select count(1) from ridden_coaster;

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

# most ridden manufacturer (always Vekoma)
select count(1) as nb, m.name from ridden_coaster r
  join coaster c on c.id = r.coaster_id
  join built_coaster bc on bc.id = c.built_coaster_id
  join manufacturer m on m.id = bc.manufacturer_id
where r.user_id = 25
group by m.id
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

# pourcentage du top 100 mondial
select count(1) as nb from ridden_coaster r
  join coaster c on c.id = r.coaster_id
where r.user_id = 93
      and c.rank < 100;

# country where I ride the most
select count(1) as nb, co.name from ridden_coaster r
  join coaster c on c.id = r.coaster_id
  join park p on p.id = c.park_id
  join country co on co.id = p.country_id
where r.user_id = 90
group by co.id
order by nb desc;

# nombre de pays
select count(distinct(co.id)) as nb from ridden_coaster r
  join coaster c on c.id = r.coaster_id
  join park p on p.id = c.park_id
  join country co on co.id = p.country_id
where r.user_id = 1
order by nb desc;

# nombre de parks
select count(distinct(p.id)) as nb from ridden_coaster r
  join coaster c on c.id = r.coaster_id
  join park p on p.id = c.park_id
where r.user_id = 90
order by nb desc;


