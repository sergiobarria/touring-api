import { eq } from 'drizzle-orm';
import slugify from 'slugify';

import { db } from '@/lib/db';
import { tours, tourImages } from '@/lib/schemas';
import type { CreateTourBody, Tour } from './validators';
import { logger } from '@/utils';

export async function createOne(data: CreateTourBody) {
	const slug = slugify(data.name as string, { lower: true });
	const { images, ...rest } = data;

	try {
		return await db.transaction(async tx => {
			// create tour
			const tourResult = await tx
				.insert(tours)
				.values({ ...rest, slug })
				.returning();

			if (!tourResult.length) return null;

			const tourId = tourResult.at(0)?.id as number;

			// create images
			if (images?.length) {
				const imagesData = images.map(url => ({ url, tourId }));
				await tx.insert(tourImages).values(imagesData).execute();
			}

			return { ...(tourResult.at(0) as Tour), images: images ?? [] };
		});
	} catch (err: unknown) {
		logger.error('💥 Error creating tour: ', err);
		throw err;
	}
}

export async function getMany() {
	return await db.select().from(tours);
}

export async function getOne(id: number) {
	const result = await db.query.tours.findFirst({
		where: eq(tours.id, id),
		with: { images: true },
	});

	return result;
}

export async function updateOne(id: number, data: Partial<CreateTourBody>) {
	const result = await db.update(tours).set(data).where(eq(tours.id, id)).returning();

	return result.length > 0 ? result.at(0) : null;
}

export async function deleteOne(id: number) {
	const result = await db.delete(tours).where(eq(tours.id, id)).returning({ deletedId: tours.id });

	if (result.length === 0) return null;

	// delete related images
	await db.delete(tourImages).where(eq(tourImages.tourId, id));

	return result.at(0);
}
