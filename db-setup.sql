/*
 Stack Maps
 
 Author(s): Jiacong Xu
 Created: May-6-2017

 This file contains SQL statements necessary to set up a new database
 supporting stack map querying and storage.

 We use http://www.sqlstyle.guide/ for naming conventions.
 */

/*
 Note on database:
 We assume a fresh database has been created for the use of this system. If not
 the case, uncommenting the following line may help.
 */

/* 
 CREATE DATABASE stack_maps;
 USE stack_maps;
 */

CREATE TABLE library (
    PRIMARY KEY (library_id),
    library_id      INT         NOT NULL    AUTO_INCREMENT,
    library_name    CHAR(25)    UNIQUE
);

CREATE TABLE floor (
    PRIMARY KEY (floor_id),
    floor_id        INT         NOT NULL    AUTO_INCREMENT,
    floor_name      CHAR(25),
    floor_order     FLOAT       NOT NULL    DEFAULT 0,
    library         INT         NOT NULL,
                    INDEX (library),
                    FOREIGN KEY (library) REFERENCES library(library_id)
                    ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE wall (
    PRIMARY KEY (wall_id),
    wall_id         INT         NOT NULL    AUTO_INCREMENT,
    start_x         FLOAT       NOT NULL    DEFAULT 0,
    start_y         FLOAT       NOT NULL    DEFAULT 0,
    end_x           FLOAT       NOT NULL    DEFAULT 0,
    end_y           FLOAT       NOT NULL    DEFAULT 0,
    floor           INT         NOT NULL,
                    INDEX(floor),
                    FOREIGN KEY (floor) REFERENCES floor(floor_id)
                    ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE aisle_area (
    PRIMARY KEY (aisle_area_id),
    aisle_area_id   INT         NOT NULL    AUTO_INCREMENT,
    center_x        FLOAT       NOT NULL    DEFAULT 0,
    center_y        FLOAT       NOT NULL    DEFAULT 0,
    width           FLOAT       NOT NULL    DEFAULT 0,
    height          FLOAT       NOT NULL    DEFAULT 0,
    rotation        FLOAT       NOT NULL    DEFAULT 0,
    floor           INT,
                    INDEX(floor),
                    FOREIGN KEY (floor) REFERENCES floor(floor_id)
                    ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE aisle (
    PRIMARY KEY (aisle_id),
    aisle_id        INT         NOT NULL    AUTO_INCREMENT,
    center_x        FLOAT       NOT NULL    DEFAULT 0,
    center_y        FLOAT       NOT NULL    DEFAULT 0,
    width           FLOAT       NOT NULL    DEFAULT 0,
    height          FLOAT       NOT NULL    DEFAULT 0,
    rotation        FLOAT       NOT NULL    DEFAULT 0,
    is_double_sided INT(1)      NOT NULL,
    aisle_area      INT         NOT NULL,
                    INDEX (aisle_area),
                    FOREIGN KEY (aisle_area) REFERENCES aisle_area(aisle_area_id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
    floor           INT,
                    INDEX (floor),
                    FOREIGN KEY (floor) REFERENCES floor(floor_id)
                    ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE call_range (
    PRIMARY KEY (call_range_id),
    call_range_id   INT         NOT NULL    AUTO_INCREMENT,
    collection      CHAR(50)    NOT NULL    DEFAULT '',
    call_start      CHAR(50)    NOT NULL    DEFAULT '',
    call_end        CHAR(50)    NOT NULL    DEFAULT '',
    side            INT,
    aisle           INT         NOT NULL,
                    INDEX(aisle),
                    FOREIGN KEY (aisle) REFERENCES aisle(aisle_id)
                    ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE landmark (
    PRIMARY KEY (landmark_id),
    landmark_id     INT         NOT NULL    AUTO_INCREMENT,
    landmark_type   CHAR(25)    NOT NULL    DEFAULT '',
    center_x        FLOAT       NOT NULL    DEFAULT 0,
    center_y        FLOAT       NOT NULL    DEFAULT 0,
    width           FLOAT       NOT NULL    DEFAULT 0,
    height          FLOAT       NOT NULL    DEFAULT 0,
    rotation        FLOAT       NOT NULL    DEFAULT 0,
    floor           INT         NOT NULL,
                    INDEX(floor),
                    FOREIGN KEY(floor) REFERENCES floor(floor_id)
                    ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE token (
    PRIMARY KEY (token_id),
    token_id        INT         NOT NULL    AUTO_INCREMENT,
    token_body      CHAR(64),
    expire_date     DATETIME
);

CREATE TABLE users (
    PRIMARY KEY (user_id),
    user_id         INT         NOT NULL    AUTO_INCREMENT,
    username CHAR(25),
    password CHAR(25)
);
