update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.seating_type_id = 2
where t.id in (72,73);

update coaster c
set c.seating_type_id = 1
where c.seating_type_id is null;

INSERT INTO model (`id`, `name`, `slug`) VALUES (1, 'SLC', 'slc');
INSERT INTO model (`id`, `name`, `slug`) VALUES (2, 'Batman', 'batman');
INSERT INTO model (`id`, `name`, `slug`) VALUES (3, 'Big Apple', 'big-apple');
INSERT INTO model (`id`, `name`, `slug`) VALUES (4, 'Big Dipper', 'big-dipper');
INSERT INTO model (`id`, `name`, `slug`) VALUES (5, 'Bobsled', 'bobsled');
INSERT INTO model (`id`, `name`, `slug`) VALUES (6, 'Boomerang', 'boomerang');
INSERT INTO model (`id`, `name`, `slug`) VALUES (7, 'Corkscrew', 'corkscrew');
INSERT INTO model (`id`, `name`, `slug`) VALUES (8, 'Dive', 'dive');
INSERT INTO model (`id`, `name`, `slug`) VALUES (9, 'El Loco', 'el-loco');
INSERT INTO model (`id`, `name`, `slug`) VALUES (10, 'Euro-Fighter', 'euro-fighter');
INSERT INTO model (`id`, `name`, `slug`) VALUES (11, 'Family Launch Coaster', 'family-launch-coaster');
INSERT INTO model (`id`, `name`, `slug`) VALUES (12, 'Free Spin', 'free-spin');
INSERT INTO model (`id`, `name`, `slug`) VALUES (13, 'Galaxi', 'galaxi');
INSERT INTO model (`id`, `name`, `slug`) VALUES (14, 'Giant Inverted Boomerang', 'giant-inverted-boomerang');
INSERT INTO model (`id`, `name`, `slug`) VALUES (15, 'Infinity', 'infinity');
INSERT INTO model (`id`, `name`, `slug`) VALUES (16, 'Invertigo', 'Invertigo');
INSERT INTO model (`id`, `name`, `slug`) VALUES (17, 'Junior Coaster', 'junior-coaster');
INSERT INTO model (`id`, `name`, `slug`) VALUES (18, 'Looping Star', 'looping-star');
INSERT INTO model (`id`, `name`, `slug`) VALUES (19, 'Megalite', 'Megalite');
INSERT INTO model (`id`, `name`, `slug`) VALUES (20, 'Screaming Squirrel', 'screaming-squirrel');
INSERT INTO model (`id`, `name`, `slug`) VALUES (21, 'Sky Rocket', 'sky-rocket');
INSERT INTO model (`id`, `name`, `slug`) VALUES (22, 'Sky Loop', 'sky-loop');
INSERT INTO model (`id`, `name`, `slug`) VALUES (23, 'SLC', 'slc');
INSERT INTO model (`id`, `name`, `slug`) VALUES (24, 'SuperSplash', 'supersplash');
INSERT INTO model (`id`, `name`, `slug`) VALUES (25, 'Tilt', 'tilt');
INSERT INTO model (`id`, `name`, `slug`) VALUES (26, 'Tivoli', 'tivoli');
INSERT INTO model (`id`, `name`, `slug`) VALUES (27, 'X Car', 'x-car');
INSERT INTO model (`id`, `name`, `slug`) VALUES (28, 'YoungStar', 'youngstar');
INSERT INTO model (`id`, `name`, `slug`) VALUES (29, 'ZacSpin', 'zacspin');
INSERT INTO model (`id`, `name`, `slug`) VALUES (30, 'Zyklon', 'Zyklon');

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 30
where t.id in (71,77);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 29
where t.id in (43);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 28
where t.id in (87);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 27
where t.id in (35);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 26
where t.id in (70,83,81);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 25
where t.id in (26);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 24
where t.id in (93);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 23
where t.id in (72,73);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 22
where t.id in (101);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 21
where t.id in (98);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 20
where t.id in (54);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 19
where t.id in (91);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 18
where t.id in (69);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 17
where t.id in (78);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 16
where t.id in (82);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 15
where t.id in (103);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 14
where t.id in (79);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 13
where t.id in (80);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 12
where t.id in (97);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 11
where t.id in (102);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 10
where t.id in (37);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 9
where t.id in (89);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 8
where t.id in (5);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 7
where t.id in (88);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 6
where t.id in (67);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 5
where t.id in (59);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 4
where t.id in (95);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 3
where t.id in (68);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 2
where t.id in (86);

update coaster c
join coaster_type ct on ct.coaster_id = c.id
join type t on t.id = ct.type_id
set c.model_id = 1
where t.id in (72,73);
