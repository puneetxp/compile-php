// Hand-written routes. compile-php copies this file once and never
// overwrites it; generated.go is rewritten on every run.
package routes

import "github.com/puneetxp/the_go/router"

// Custom routes, for example:
//
//	{Islogin: true, Child: []router.Route{
//		{Path: "/islogin/book/:book_id/listing", Group: map[string][]router.Route{
//			"GET":  {{Handler: listing.Show}},
//			"POST": {{Handler: listing.Update}},
//		}},
//	}},
func Custom() []router.Route {
	return nil
}
