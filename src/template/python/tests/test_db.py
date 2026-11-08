"""
Model and DB against a real database: PostgreSQL by default, MySQL/MariaDB
with DBDRIVER=mysql. Creates and drops the_t_items / the_t_categories.
    DBTEST=1 DBDRIVER=mysql DBHOST=127.0.0.1 DBUSER=root DBNAME=the_test python3 -m unittest tests.test_db
"""
import os
import unittest

from app.core import db as dbmod
from app.core.db import DB
from app.core.model import Model


class Category(Model):
    name = "category"
    table = "the_t_categories"
    columns = ["id", "title"]
    fillable = ["title"]
    relations = {}


class Item(Model):
    name = "item"
    table = "the_t_items"
    columns = ["id", "name", "order", "enable", "price", "category_id"]
    fillable = ["name", "order", "enable", "price", "category_id"]
    relations = {"category": {"table": "the_t_categories", "name": "category_id", "key": "id", "callback": Category}}


Category.relations = {"item": {"table": "the_t_items", "name": "id", "key": "category_id", "callback": Item}}


@unittest.skipUnless(os.getenv("DBTEST") == "1", "set DBTEST=1 to run against a database")
class RealDatabase(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        pg = dbmod.get_db_settings().driver == "postgres"
        serial = "bigserial" if pg else "bigint unsigned AUTO_INCREMENT"
        fk = "bigint" if pg else "bigint unsigned"
        DB.raw("DROP TABLE IF EXISTS the_t_items")
        DB.raw("DROP TABLE IF EXISTS the_t_categories")
        DB.raw(f"CREATE TABLE the_t_categories (id {serial} PRIMARY KEY, title varchar(100) NOT NULL)")
        DB.raw(f"CREATE TABLE the_t_items (id {serial} PRIMARY KEY, name varchar(100) NOT NULL, `order` int NOT NULL DEFAULT 0, "
               f"enable smallint NOT NULL DEFAULT 1, price numeric(10,2) NOT NULL DEFAULT 0, category_id {fk} NULL REFERENCES the_t_categories(id))")

    @classmethod
    def tearDownClass(cls):
        DB.raw("DROP TABLE IF EXISTS the_t_items")
        DB.raw("DROP TABLE IF EXISTS the_t_categories")
        dbmod.close()

    def test_everything(self):
        names = lambda m: [r["name"] for r in m.items]
        tea = Category.create({"title": "Tea"}).get_inserted().items
        Category.create({"title": "Empty"})
        self.assertIsInstance(tea["id"], int)

        a = Item.create({"name": "Assam", "order": 2, "price": "120.50", "category_id": tea["id"]}).get_inserted().items
        self.assertEqual((a["name"], a["order"], a["price"], a["enable"]), ("Assam", 2, "120.50", 1),
                         "reserved column `order`, DECIMAL as a string")

        many = Item.insert([{"name": "Darjeeling", "order": 1}, {"name": "Nilgiri", "order": 3}, {"name": "Kangra", "order": 4}]).get_all_inserted()
        self.assertEqual(sorted(names(many)), ["Darjeeling", "Kangra", "Nilgiri"], "insert many + read every inserted row")

        self.assertEqual(names(Item.where({"name": ["Assam", "Kangra"]}).where({"order": 4}).get()), ["Kangra"], "chained where")
        self.assertEqual(Item.find("Nilgiri", "name").items["order"], 3)

        Item.where({"name": ["Nilgiri"]}).update({"name": "Nilgiri FTGFOP"})
        self.assertEqual(Item.find("Nilgiri FTGFOP", "name").items["order"], 3, "where().update()")

        self.assertEqual(names(Item.where_has("category", {"title": "Tea"}).get()), ["Assam"], "where_has")
        self.assertEqual([r["title"] for r in Category.where_has("item", lambda i: i.where({"name": "Assam"})).get().items], ["Tea"])
        self.assertEqual([r["title"] for r in Category.where_not_has("item").get().items], ["Empty"], "where_not_has")
        self.assertEqual(names(Item.order_by("order", "DESC").limit(2).get()), ["Kangra", "Nilgiri FTGFOP"], "order_by + limit")

        joined = Item.where_has("category", {"title": "Tea"}).order_by("id").with_join(["category"]).items
        self.assertEqual((len(joined), joined[0]["name"], joined[0]["category"]["title"]), (1, "Assam", "Tea"), "with_join")

        existing = Item.find("Kangra", "name").items
        before = len(Item.all().items)
        Item.upsert([{"id": existing["id"], "name": "Kangra Gold", "order": 4}])
        self.assertEqual(Item.find(existing["id"]).items["name"], "Kangra Gold", "upsert by id updates")
        self.assertEqual(len(Item.all().items), before, "and does not insert")

        self.assertEqual(Item.delete({"name": ["Darjeeling"]}), 1, "Model.delete(where) returns the count")
        rec = Item.find("Kangra Gold", "name")
        self.assertTrue(rec.delete(), "record.delete() deletes that record")
        self.assertIsNone(Item.find("Kangra Gold", "name"))
        self.assertEqual(Item.where({"id": []}).get().items, [], "an empty list matches nothing")


if __name__ == "__main__":
    unittest.main()
