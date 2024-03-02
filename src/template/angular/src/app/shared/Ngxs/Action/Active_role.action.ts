import { Active_role } from "../../Interface/Model/Active_role";


export class SetActive_role {
  static readonly type = '[ACTIVE_ROLES] set Active_role';
  constructor(public payload: Active_role[]) { }
}

export class AddActive_role {
  static readonly type = '[ACTIVE_ROLES] Add Active_role';
  constructor(public payload: Active_role) { }
}

export class EditActive_role {
  static readonly type = '[ACTIVE_ROLES] edit';
  constructor(public payload: Active_role) { }
}

export class DeleteActive_role {
  static readonly type = '[ACTIVE_ROLES] delete';
  constructor(public payload: number) { }
}
export class InsertActive_role {
  static readonly type = '[ACTIVE_ROLES] insert';
  constructor(public payload: Active_role[]) { }
}
export class UpsertActive_role {
  static readonly type = '[ACTIVE_ROLES] upsert';
  constructor(public payload: Active_role[]) { }
}
export class DeleteRoleByUser {
  static readonly type = '[ACTIVE_ROLES] deletebyuserid';
  constructor(public payload: number) { }
}