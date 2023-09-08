# Bugs in bexio API or documentation

  * In the documentation, partial update are said to be done with PATCH verb.
  But when you use patch, you get a 404 error. If you replace PATH with POST,
  same URL and body, you get a partial update.
  * If you request a contact that have no salutation set, the attribute
  salutation_id is set to 0 instead of null. If you POST back the contact
  without setting salutation_id to null, you get an error.
  * When requesting a country, you have attribute iso_3166_alpha2 set with
  the value, according to documentation, that should be set in property
  iso3166_alpha2. That property is set to null instead. If you send back the
  same object, the server will fail, you have to set iso3166_alpha2 to the
  value of iso_3166_alpha2 and remove iso_3166_alpha2 
  * When requesting Bills, status has nothing to do with what is documented, I am
  still trying to figure out what means what.
  * On the Bills endpoint, limit and page are a bit strange as if you use them
  in some case it return '[400] page: unsupported value type' in some case not,
  still trying to figure out.
  * For File, the documentation says the id [int32] must be used when in fact 
  you need to use the uuid [string].
  * A GET request without body and without Content-Length: 0 as header can have
  the API returning an error.