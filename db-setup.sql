/*
 * Stack Maps
 *
 * Author(s): Jiacong Xu
 * Created: May-6-2017
 *
 * This file contains SQL statements necessary to set up a new database
 * supporting stack map querying and storage.
 */

 /*create database LibraryStacks;*/

 create table Library (
    lid INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    lname CHAR(25) NOT NULL
 )  ENGINE=INNODB;

 create table Floor (
     fid INT NOT NULL AUTO_INCREMENT,
     fname CHAR(25) NOT NULL,
     forder FLOAT,
     library INT NOT NULL,

     PRIMARY KEY(fid),
     INDEX (library),

     FOREIGN KEY (library)
       REFERENCES Library(lid)
       ON UPDATE CASCADE ON DELETE RESTRICT
 )   ENGINE=INNODB;

 create table Wall(
     wid INT NOT NULL AUTO_INCREMENT,
     x1 FLOAT,
     y1 FLOAT,
     x2 FLOAT,
     y2 FLOAT,
     floor INT,

     PRIMARY KEY(wid),
     INDEX(floor),
     FOREIGN KEY (floor)
       REFERENCES Floor(fid)
       ON UPDATE CASCADE ON DELETE RESTRICT
 )   ENGINE=INNODB;

 /* AisleArea is an area that has aisles */
 create table AisleArea(
     aaid INT(9) NOT NULL PRIMARY KEY AUTO_INCREMENT,
     center_x FLOAT(9),
     center_y FLOAT(9),
     length FLOAT(9),
     width FLOAT(9),
     rotation FLOAT(9),
     floor INT,

     INDEX(floor),
     FOREIGN KEY(floor)
       REFERENCES Floor(fid)
       ON UPDATE CASCADE ON DELETE RESTRICT
 )   ENGINE=INNODB;

 /* Aisle is for book stacks that hold books */
 create table Aisle(
     aid INT(9) NOT NULL PRIMARY KEY AUTO_INCREMENT,
     center_x FLOAT(9),
     center_y FLOAT(9),
     length FLOAT(9),
     width FLOAT(9),
     rotation FLOAT(9),
     sides INT(1),
     category CHAR(10),
     aislearea INT,
     floor INT,

     INDEX(aislearea),
     FOREIGN KEY(aislearea)
       REFERENCES AisleArea(aaid)
       ON UPDATE CASCADE ON DELETE CASCADE,
     INDEX(floor),
       FOREIGN KEY(floor)
         REFERENCES Floor(fid)
         ON UPDATE CASCADE ON DELETE RESTRICT
 );

/* Call_Range is used to save the call ranges for each aisle*/
create table Call_Range(
    cid INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    collection CHAR(50),
    callstart CHAR(50),
    callend CHAR(50),
    side INT,
    aisle INT(9),

    INDEX(aisle),
    FOREIGN KEY(aisle)
      REFERENCES Aisle(aid)
      ON UPDATE CASCADE ON DELETE CASCADE
);

 /* Landmark is for icons other than book stacks, such as elevators, stairs, etc. */
 create table Landmark(
     lmid INT(9) NOT NULL PRIMARY KEY AUTO_INCREMENT,
     lname CHAR(25),
     center_x FLOAT(9),
     center_y FLOAT(9),
     rotation FLOAT(9),
     length FLOAT(9),
     width FLOAT(9),
     floor INT,

     INDEX(floor),
     FOREIGN KEY(floor)
       REFERENCES Floor(fid)
       ON UPDATE CASCADE ON DELETE RESTRICT
 );

 /* Create token table*/
 create table Token(
     tid INT(9) NOT NULL PRIMARY KEY AUTO_INCREMENT,
     token CHAR(32),
     expiration DATETIME
 );

 /* Create table to save username and password*/
 create table Users(
     username CHAR(25),
     password CHAR(25)
 );
