// The app's entry point. compile-php copies this file once and never
// overwrites it.
package main

import (
	"database/sql"
	"fmt"
	"log"
	"net/http"
	"os"

	_ "github.com/go-sql-driver/mysql"
	_ "github.com/jackc/pgx/v5/stdlib"

	"app/routes"

	"github.com/puneetxp/the_go/model"
	"github.com/puneetxp/the_go/router"
	"github.com/puneetxp/the_go/session"
)

func env(k, d string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return d
}

func main() {
	var db *sql.DB
	var err error
	if env("DBDRIVER", "mysql") == "postgres" {
		db, err = sql.Open("pgx", fmt.Sprintf("postgres://%s:%s@%s:%s/%s",
			env("DBUSER", ""), env("DBPWD", ""), env("DBHOST", "localhost"), env("DBPORT", "5432"), env("DBNAME", "")))
		model.Use(db, model.Postgres)
	} else {
		// interpolateParams: one round trip per query.
		db, err = sql.Open("mysql", fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?interpolateParams=true&parseTime=true",
			env("DBUSER", ""), env("DBPWD", ""), env("DBHOST", "localhost"), env("DBPORT", "3306"), env("DBNAME", "")))
		model.Use(db, model.MySQL)
	}
	if err != nil {
		log.Fatal(err)
	}

	// CORS=on to let other sites call the API; CORS_ORIGINS limits it, e.g.
	// https://app.example.com (comma separated). Off by default.
	corsOn, origins := router.CORSFromEnv()
	r, err := router.New(routes.All(), router.Options{
		Cookie:  session.CookieOptions{Domain: env("COOKIE_DOMAIN", ""), Secure: env("COOKIE_SECURE", "") != ""},
		CORS:    corsOn,
		Origins: origins,
	})
	if err != nil {
		log.Fatal(err)
	}
	addr := ":" + env("PORT", "9000")
	log.Printf("listening on %s", addr)
	log.Fatal(http.ListenAndServe(addr, r))
}
