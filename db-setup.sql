/*
 * Stack Maps
 *
 * Author(s): Jiacong Xu
 * Created: May-6-2017
 *
 * This file contains SQL statements necessary to set up a new database
 * supporting stack map querying and storage.
 */

 create database LibraryStacks;

 create table Library(
     lid INT(5) NOT NULL AUTO_INCREMENT,
     name CHAR(25) PRIMARY KEY
 );

 create table Floor(
     fid INT(9) NOT NULL AUTO_INCREMENT,
     name CHAR(25),
     FOREIGN KEY(library)
       REFERENCES Library(name)
       ON UPDATE CASCADE ON DELETE RESTRICT,
    PRIMARY KEY(name, library)
 );

 create table Wall(
     wid INT(9) NOT NULL PRIMARY KEY AUTO_INCREMENT,
     x1 INT(9),
     y1 INT(9),
     x2 INT(9),
     y2 INT(9)
     FOREIGN KEY(floor)
       REFERENCES Floor(name)
       ON UPDATE CASCADE ON DELETE RESTRICT
 );

 /* AisleArea is an area that has aisles */
 create table AisleArea(
     aaid INT(9) NOT NULL PRIMARY KEY AUTO_INCREMENT,
     center_x INT(9),
     center_y INT(9),
     length INT(9),
     width INT(9),
     rotation INT(9),
     FOREIGN KEY(floor)
       REFERENCES Floor(name)
       ON UPDATE CASCADE ON DELETE RESTRICT
 );

 /* Aisle is for book stacks that hold books */
 create table Aisle(
     aid INT(9) NOT NULL PRIMARY KEY AUTO_INCREMENT,
     center_x INT(9),
     center_y INT(9),
     length INT(9),
     width INT(9),
     rotation INT(9),
     sides INT(1),
     category CHAR(10),
     call_range CHAR(1000),
     FOREIGN KEY(aislearea)
       REFERENCES AisleArea(aaid)
       ON UPDATE CASCADE ON DELETE RESTRICT
 );

 /* Landmark is for icons other than book stacks, such as elevators, stairs, etc. */
 create table Landmark(
     lmid INT(9) NOT NULL PRIMARY KEY AUTO_INCREMENT,
     name CHAR(25),
     center_x INT(9),
     center_y INT(9),
     length INT(9),
     width INT(9),
     FOREIGN KEY(floor)
       REFERENCES Floor(name)
       ON UPDATE CASCADE ON DELETE RESTRICT
 );

 /* Create token table*/
 create table Token(
     tid INT(9) NOT NULL PRIMARY KEY AUTO_INCREMENT,
     token CHAR(25),
     expiration DATETIME(20),
 );
