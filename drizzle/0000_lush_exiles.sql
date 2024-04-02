DO $$ BEGIN
 CREATE TYPE "difficulty" AS ENUM('easy', 'medium', 'difficult');
EXCEPTION
 WHEN duplicate_object THEN null;
END $$;
--> statement-breakpoint
CREATE TABLE IF NOT EXISTS "touric_dev_images" (
	"id" serial PRIMARY KEY NOT NULL,
	"url" text NOT NULL,
	"tour_id" integer NOT NULL,
	"created_at" timestamp with time zone DEFAULT now(),
	"updated_at" timestamp with time zone DEFAULT now()
);
--> statement-breakpoint
CREATE TABLE IF NOT EXISTS "touric_dev_tours" (
	"id" serial PRIMARY KEY NOT NULL,
	"name" varchar(256),
	"slug" varchar(256),
	"duration" integer NOT NULL,
	"max_group_size" integer NOT NULL,
	"difficulty" "difficulty" NOT NULL,
	"rating_avg" numeric(2, 1) DEFAULT '0.0' NOT NULL,
	"rating_qty" integer DEFAULT 0 NOT NULL,
	"price" numeric(10, 2) NOT NULL,
	"price_discount" numeric(10, 2),
	"summary" text NOT NULL,
	"description" text NOT NULL,
	"cover_image" text,
	"start_dates" text[],
	"created_at" timestamp with time zone DEFAULT now(),
	"updated_at" timestamp with time zone DEFAULT now(),
	CONSTRAINT "touric_dev_tours_name_unique" UNIQUE("name"),
	CONSTRAINT "touric_dev_tours_slug_unique" UNIQUE("slug")
);
