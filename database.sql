INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (1, 'Sit Down', 'sit-down');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 1
where ct.type_id = 41;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (2, 'Inverted', 'inverted');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 2
where ct.type_id = 12;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (3, 'Stand Up', 'stand-up');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 3
where ct.type_id = 24;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (4, 'Flying', 'flying');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 4
where ct.type_id = 8;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (5, 'Floorless', 'floorless');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 5
where ct.type_id = 7;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (6, 'Suspended', 'suspended');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 6
where ct.type_id = 25;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (7, 'Wing', 'wing');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 7
where ct.type_id = 52;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (8, 'Spinning', 'spinning');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 8
where ct.type_id = 21;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (9, 'Motorbike', 'motorbike');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 9
where ct.type_id = 36;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (10, 'Bobsleigh', 'bobsleigh');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 10
where ct.type_id = 4;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (11, '4th Dimension', '4th-dimension');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 11
where ct.type_id = 2;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (12, 'Pipeline', 'pipeline');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 12
where ct.type_id = 17;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (13, 'Alpine', 'alpine');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 13
where ct.type_id = 57;
INSERT INTO `seating_type` (`id`, `name`, `slug`) VALUES (14, 'Water Coaster', 'water-coaster');
update coaster c
join coaster_type ct on ct.coaster_id = c.id
set c.seating_type_id = 14
where ct.type_id = 27;

delete from coaster_type where type_id IN (41,12,24,8,7,25,52,21,36,4,2,17,57,27);
delete from type where id IN (41,12,24,8,7,25,52,21,36,4,2,17,57,27);

###

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
