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
