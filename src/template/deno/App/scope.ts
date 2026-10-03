import { response, Session } from "../dep.ts";

/**
 * "under": the parent row named in the URL (for example :book_id) must belong to the
 * logged-in user. Returns the parent id, or a 404 Response to send back as-is.
 * 404 rather than 403, so nobody can probe which ids exist.
 */
export async function ownedParent(
  session: Session,
  param: URLPatternResult,
  // deno-lint-ignore no-explicit-any
  parent: () => { where(w: any): { first(): Promise<unknown> } },
  key: string,
  ownerColumn: string,
): Promise<number | Response> {
  const id = Number(param.pathname.groups[key]);
  if (!Number.isInteger(id) || id <= 0) {
    return response.JSON("Not Found", session, 404);
  }
  const row = await parent().where({ id: [id], [ownerColumn]: [session.Login.id] }).first();
  if (!row) {
    return response.JSON("Not Found", session, 404);
  }
  return id;
}
