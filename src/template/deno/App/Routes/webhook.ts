export const webhook = [{
  path: "/webhook",
  method: "POST",
  handler: async () => {
    Deno.run({ cmd: ["/var/www/github/the_billing/gitpush.sh"] });
    return await new Response("It should work");
  },
}];
