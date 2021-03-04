CREATE TABLE {{$table_prefix}}requests_per_day
(
    id                        int(11)         NOT NULL AUTO_INCREMENT,
    user_ip                   varchar(50)     NOT NULL,
    requests_number           int(11)         NOT NULL,
    request_date              date            NOT NULL,

    PRIMARY KEY (id)
);

CREATE TABLE {{$table_prefix}}requests_per_minute
(
    id                        int(11)         NOT NULL AUTO_INCREMENT,
    user_ip                   varchar(50)     NOT NULL,
    requests_number           int(11)         NOT NULL,
    request_datetime          datetime        NOT NULL,

    PRIMARY KEY (id)
);

CREATE TABLE {{$table_prefix}}black_list
(
    id                        int(11)         NOT NULL AUTO_INCREMENT,
    user_ip                   varchar(50)     NOT NULL,
    request_date              date            NOT NULL,

    PRIMARY KEY (id)
);

CREATE TABLE {{$table_prefix}}post_types
(
    id                        int(11)         NOT NULL AUTO_INCREMENT,
    post_type                 varchar(50)     NOT NULL,
    request_date              date            NOT NULL,

    PRIMARY KEY (id)
);
