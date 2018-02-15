INSERT INTO tag
SELECT null, name, 'pro' from positive_keyword;

INSERT INTO tag
SELECT null, name, 'con' from negative_keyword;

INSERT INTO ridden_coaster_pro
SELECT ridden_coaster_id, positive_keyword_id from ridden_coaster_positive_keyword;

INSERT INTO ridden_coaster_con
SELECT ridden_coaster_id, negative_keyword_id + 31 from ridden_coaster_negative_keyword;
