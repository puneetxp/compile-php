import { _Routes } from "../../dep.ts";
import { IsloginAccountController } from "../Controller/Islogin/AccountController.ts";
import { IsloginAccount_attribute_valueController } from "../Controller/Islogin/Account_attribute_valueController.ts";
import { IsloginBookController } from "../Controller/Islogin/BookController.ts";
import { IsloginBook_detailController } from "../Controller/Islogin/Book_detailController.ts";
import { IsloginGstnController } from "../Controller/Islogin/GstnController.ts";
import { IsloginJournalController } from "../Controller/Islogin/JournalController.ts";
import { IsloginJournal_detailController } from "../Controller/Islogin/Journal_detailController.ts";
import { IsloginOrderController } from "../Controller/Islogin/OrderController.ts";
import { IsloginServiceController } from "../Controller/Islogin/ServiceController.ts";
import { IsloginSubscriptionController } from "../Controller/Islogin/SubscriptionController.ts";
export const islogin: _Routes = [{
  "path": "islogin",
  "child": [
    {
      "path": "/account",
      "crud": {
        "class": IsloginAccountController,
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
        "class": IsloginAccount_attribute_valueController,
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
      "path": "/book",
      "crud": {
        "class": IsloginBookController,
        "crud": [
          "c",
          "r",
          "u",
          "d",
          "all",
          "where",
        ],
      },
      child: [{
        path: "/.+",
        child: [
          {
            path: "/template",
            child: [{
              path: "/sales",
              handler: IsloginAccountController.salestemplate,
            }],
          },
          {
            path: "/account",
            group: {
              "GET": [{ handler: IsloginAccountController.all }],
            },
          },
          {
            path: "/journal",
            group: {
              "POST": [{ handler: IsloginJournalController.store }],
            },
          },
        ],
      }],
    },
    {
      "path": "/book_detail",
      "crud": {
        "class": IsloginBook_detailController,
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
        "class": IsloginGstnController,
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
      "path": "/journal",
      "crud": {
        "class": IsloginJournalController,
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
        "class": IsloginJournal_detailController,
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
        "class": IsloginOrderController,
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
      "path": "/service",
      "crud": {
        "class": IsloginServiceController,
        "crud": [
          "r",
          "where",
          "all",
        ],
      },
    },
    {
      "path": "/subscription",
      "crud": {
        "class": IsloginSubscriptionController,
        "crud": [
          "r",
          "where",
          "all",
        ],
      },
    },
  ],
}];
