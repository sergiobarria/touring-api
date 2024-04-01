CREATE TABLE IF NOT EXISTS "touric_dev_tours" (
	"id" serial PRIMARY KEY NOT NULL,
	"name" varchar(256),
	"rating_avg" numeric(2, 1) NOT NULL,
	"price" numeric(10, 2) NOT NULL,
	CONSTRAINT "touric_dev_tours_name_unique" UNIQUE("name")
);
