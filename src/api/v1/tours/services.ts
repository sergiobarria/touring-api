import { db } from '@/lib/db';
import { tours } from '@/lib/schemas';
import type { CreateTourBody } from './validators';
import { eq } from 'drizzle-orm';

export async function createOne(data: CreateTourBody) {
	return await db.insert(tours).values(data).returning(); // returns the entire inserted row
}

export async function getMany() {
	return await db.select().from(tours);
}

export async function getOne(id: number) {
	const result = await db.select().from(tours).where(eq(tours.id, id));

	return result.length > 0 ? result.at(0) : null;
}

export async function updateOne(id: number, data: Partial<CreateTourBody>) {
	const result = await db.update(tours).set(data).where(eq(tours.id, id)).returning();
	console.log('🚀 ~ updateOne ~ result:', result);
	return result.length > 0 ? result.at(0) : null;
}

export async function deleteOne(id: number) {
	const result = await db.delete(tours).where(eq(tours.id, id)).returning({ deletedId: tours.id });

	return result.length > 0 ? result.at(0)?.deletedId : null;
}
