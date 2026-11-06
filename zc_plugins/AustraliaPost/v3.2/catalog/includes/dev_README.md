# aupostoverseas changes
duplicate code moved to functions; check and recover for invalid destination country;
 *      normalise: single service returned as object rather than array for when only one option is returned
 
 
Effect: the moment AP reports an unrecognised ISO code (Andorra/AD), the module resets both variables, clears the cached quotes and the selected method, tells the customer via the checkout message stack, and bounces them to the shipping-address page where they must pick a different destination country. On non-checkout pages (e.g. the estimator popup) it stays silent and just returns to prior behaviour.



# aupost
normalise 'services/service' when Australia Post returns a single service as an object rather than an
 *              array, so the $i-indexed loop handles both single and multiple service responses without
 *              "Undefined array key 0"