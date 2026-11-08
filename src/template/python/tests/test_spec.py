"""
Runs spec/query_cases.json: the query builder cases every adapter of the
framework must pass (the same file as the_deno, the_lib and the_go).
No database needed: to_sql() / with_join_sql() don't run SQL.
    python3 -m unittest tests.test_spec
"""
import json
import pathlib
import re
import unittest

from app.core.db import driver_sql
from app.core.model import Model

SPEC = json.loads((pathlib.Path(__file__).parent.parent / "spec" / "query_cases.json").read_text())
MODELS = {}


def make(name):
    return MODELS[name]()


for _name, _m in SPEC["models"].items():
    MODELS[_name] = type(f"Spec_{_name}", (Model,), {
        "name": _name,
        "table": _m["table"],
        "columns": _m["columns"],
        "fillable": _m["columns"],
        "relations": {rel: {"table": r["table"], "name": r["name"], "key": r["key"],
                            "callback": (lambda model=r["model"]: make(model))}
                      for rel, r in _m["relations"].items()},
    })


def replay(model, chain):
    for step in chain:
        op, args = step[0], step[1:]
        if op == "where":
            model.where(args[0], args[1] if len(args) > 1 else False)
        elif op in ("whereHas", "whereNotHas"):
            cond = args[1] if len(args) > 1 else None
            if isinstance(cond, list):
                cond = (lambda steps: (lambda r: replay(r, steps)))(cond)
            (model.where_has if op == "whereHas" else model.where_not_has)(args[0], cond)
        elif op == "orderBy":
            model.order_by(*args)
        elif op == "limit":
            model.limit(*args)
        elif op == "withJoin":
            pass  # the last step; sql_of handles it
        else:
            raise ValueError(f'spec uses unknown step "{op}"')
    return model


def sql_of(case):
    last = case["chain"][-1]
    model = replay(make(case["model"]), case["chain"])
    return model.with_join_sql(last[1]) if last[0] == "withJoin" else model.to_sql()


class Spec(unittest.TestCase):
    def test_cases(self):
        for case in SPEC["cases"]:
            with self.subTest(case["name"]):
                got = sql_of(case)
                self.assertEqual(re.sub(r"\s+", " ", got["sql"]).strip(), case["sql"])
                self.assertEqual(got["params"], case["params"])
                self.assertEqual(sql_of(case)["sql"], got["sql"], "same SQL on a second run")

    def test_errors(self):
        for case in SPEC["errors"]:
            with self.subTest(case["name"]):
                with self.assertRaisesRegex(Exception, re.escape(case["message"])):
                    sql_of(case)

    def test_static_and_chained_where_agree(self):
        product = MODELS["product"]
        self.assertEqual(product.where({"id": 1}).where({"enable": 1}).to_sql()["params"], [1, 1])

    def test_to_sql_clears_the_chain(self):
        m = make("product").where({"enable": 1}).order_by("id").limit(1)
        m.to_sql()
        self.assertEqual(m.to_sql()["sql"].strip(), "SELECT * FROM products")

    def test_driver_sql(self):
        nested = next(c for c in SPEC["cases"] if c["name"].startswith("nested whereHas"))
        self.assertEqual(
            driver_sql(nested["sql"], postgres=True),
            'SELECT * FROM books WHERE EXISTS (SELECT 1 FROM book_stocks AS "book_stocks__1" WHERE "book_stocks__1"."book_id" = "books"."id" AND ("publish" IN (%s) AND EXISTS (SELECT 1 FROM products AS "products__2" WHERE "products__2"."id" = "book_stocks__1"."product_id" AND ("enable" IN (%s)))))')
        self.assertEqual(driver_sql("SELECT '50% ?', `a` FROM t WHERE x LIKE ? -- ?", postgres=False),
                         "SELECT '50%% ?', `a` FROM t WHERE x LIKE %s -- ?")


if __name__ == "__main__":
    unittest.main()
