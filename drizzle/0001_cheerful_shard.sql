ALTER TABLE "touric_dev_tours" ALTER COLUMN "rating_avg" SET DEFAULT '4.50';--> statement-breakpoint
ALTER TABLE "touric_dev_tours" ADD COLUMN "duration" integer NOT NULL;--> statement-breakpoint
ALTER TABLE "touric_dev_tours" ADD COLUMN "max_group_size" integer NOT NULL;--> statement-breakpoint
ALTER TABLE "touric_dev_tours" ADD COLUMN "difficulty" varchar(40) NOT NULL;--> statement-breakpoint
ALTER TABLE "touric_dev_tours" ADD COLUMN "rating_qty" integer DEFAULT 0 NOT NULL;--> statement-breakpoint
ALTER TABLE "touric_dev_tours" ADD COLUMN "summary" text NOT NULL;--> statement-breakpoint
ALTER TABLE "touric_dev_tours" ADD COLUMN "description" text NOT NULL;--> statement-breakpoint
ALTER TABLE "touric_dev_tours" ADD COLUMN "created_at" timestamp with time zone DEFAULT now();--> statement-breakpoint
ALTER TABLE "touric_dev_tours" ADD COLUMN "updated_at" timestamp with time zone DEFAULT now();