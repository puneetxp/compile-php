import { IsuperAccountController } from "../Controller/Isuper/AccountController.ts";
import { IsuperAccount_attributeController } from "../Controller/Isuper/Account_attributeController.ts";
import { IsuperAccount_attribute_valueController } from "../Controller/Isuper/Account_attribute_valueController.ts";
import { IsuperAccount_typeController } from "../Controller/Isuper/Account_typeController.ts";
import { IsuperActive_roleController } from "../Controller/Isuper/Active_roleController.ts";
import { IsuperBillController } from "../Controller/Isuper/BillController.ts";
import { IsuperBookController } from "../Controller/Isuper/BookController.ts";
import { IsuperBook_attributeController } from "../Controller/Isuper/Book_attributeController.ts";
import { IsuperBook_detailController } from "../Controller/Isuper/Book_detailController.ts";
import { IsuperGstnController } from "../Controller/Isuper/GstnController.ts";
import { IsuperJournalController } from "../Controller/Isuper/JournalController.ts";
import { IsuperJournal_detailController } from "../Controller/Isuper/Journal_detailController.ts";
import { IsuperOrderController } from "../Controller/Isuper/OrderController.ts";
import { IsuperPaymentController } from "../Controller/Isuper/PaymentController.ts";
import { IsuperPayment_methodController } from "../Controller/Isuper/Payment_methodController.ts";
import { IsuperRoleController } from "../Controller/Isuper/RoleController.ts";
import { IsuperServiceController } from "../Controller/Isuper/ServiceController.ts";
import { IsuperService_attributeController } from "../Controller/Isuper/Service_attributeController.ts";
import { IsuperService_attribute_valueController } from "../Controller/Isuper/Service_attribute_valueController.ts";
import { IsuperService_planController } from "../Controller/Isuper/Service_planController.ts";
import { IsuperService_plan_priceController } from "../Controller/Isuper/Service_plan_priceController.ts";
import { IsuperSubscriptionController } from "../Controller/Isuper/SubscriptionController.ts";
import { IsuperUserController } from "../Controller/Isuper/UserController.ts";
export const isuper = [{
  "path": "/isuper",
  "child": [
    {
      "path": "/account",
      "crud": {
        "class": IsuperAccountController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "all",
          "upsert",
          "where",
        ],
      },
    },
    {
      "path": "/account_attribute",
      "crud": {
        "class": IsuperAccount_attributeController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "all",
          "upsert",
          "where",
        ],
      },
    },
    {
      "path": "/account_attribute_value",
      "crud": {
        "class": IsuperAccount_attribute_valueController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "all",
          "upsert",
          "where",
        ],
      },
    },
    {
      "path": "/account_type",
      "crud": {
        "class": IsuperAccount_typeController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "where",
          "all",
          "upsert",
        ],
      },
    },
    {
      "path": "/active_role",
      "crud": {
        "class": IsuperActive_roleController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "all",
        ],
      },
    },
    {
      "path": "/bill",
      "crud": {
        "class": IsuperBillController,
        "crud": [
          "c",
          "r",
          "u",
        ],
      },
    },
    {
      "path": "/book",
      "crud": {
        "class": IsuperBookController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "all",
          "upsert",
          "where",
        ],
      },
    },
    {
      "path": "/book_attribute",
      "crud": {
        "class": IsuperBook_attributeController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "all",
          "upsert",
        ],
      },
    },
    {
      "path": "/book_detail",
      "crud": {
        "class": IsuperBook_detailController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "all",
          "upsert",
          "where",
        ],
      },
    },
    {
      "path": "/gstn",
      "crud": {
        "class": IsuperGstnController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "all",
          "upsert",
          "where",
        ],
      },
    },
    {
      "path": "/journal",
      "crud": {
        "class": IsuperJournalController,
        "crud": [
          "c",
          "r",
          "all",
          "upsert",
          "where",
        ],
      },
    },
    {
      "path": "/journal_detail",
      "crud": {
        "class": IsuperJournal_detailController,
        "crud": [
          "c",
          "r",
          "all",
          "upsert",
          "where",
        ],
      },
    },
    {
      "path": "/order",
      "crud": {
        "class": IsuperOrderController,
        "crud": [
          "c",
          "r",
          "u",
          "all",
          "where",
        ],
      },
    },
    {
      "path": "/payment",
      "crud": {
        "class": IsuperPaymentController,
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
    {
      "path": "/payment_method",
      "crud": {
        "class": IsuperPayment_methodController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "all",
          "upsert",
          "where",
        ],
      },
    },
    {
      "path": "/role",
      "crud": {
        "class": IsuperRoleController,
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
    {
      "path": "/service",
      "crud": {
        "class": IsuperServiceController,
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
    {
      "path": "/service_attribute",
      "crud": {
        "class": IsuperService_attributeController,
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
    {
      "path": "/service_attribute_value",
      "crud": {
        "class": IsuperService_attribute_valueController,
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
    {
      "path": "/service_plan",
      "crud": {
        "class": IsuperService_planController,
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
    {
      "path": "/service_plan_price",
      "crud": {
        "class": IsuperService_plan_priceController,
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
    {
      "path": "/subscription",
      "crud": {
        "class": IsuperSubscriptionController,
        "crud": [
          "c",
          "r",
          "all",
          "upsert",
          "where",
        ],
      },
    },
    {
      "path": "/user",
      "crud": {
        "class": IsuperUserController,
        "crud": [
          "r",
          "u",
          "all",
          "where",
        ],
      },
    },
  ],
  "roles": [
    "isuper",
  ],
}];
