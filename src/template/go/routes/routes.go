// compile-php copies this file once and never overwrites it.
package routes

import (
	"github.com/puneetxp/the_go/auth"
	"github.com/puneetxp/the_go/router"
)

// All is every route of the app: generated CRUD, hand-written routes and auth.
func All() []router.Route {
	all := []router.Route{{Path: "/", Handler: func(c *router.Ctx) error { return c.JSON(200, "ok") }}}
	all = append(all, Generated()...)
	all = append(all, Custom()...)
	return append(all, auth.Routes()...)
}
