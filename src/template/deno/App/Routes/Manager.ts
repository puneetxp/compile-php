import { ManagerService_plan_priceController } from "../Controller/Manager/Service_plan_priceController.ts";
export const manager = [{
  "path": "/manager",
  "child": [
    {
      "path": "/service_plan_price",
      "crud": {
        "class": ManagerService_plan_priceController,
        "crud": [
          "c",
          "r",
          "u",
          "all",
          "upsert",
          "where",
        ],
      },
    },
  ],
  "roles": [
    "manager",
  ],
}];
