# STD Rest >> CoffeeScript (Javascript)

This represents an implentation of the idea in CoffeeScipt. Minified, it's about 9kb.

The main difference is handling async and so all calls require a callback function somewhere in them:

	a = new APIFactory();
	...
	a.store.get_shipping_rate(4525, function(data) {
		// do something with data here
	})

The only function addition of note is fromFile() (which is truly "from url") which loads via AJAX the URL referenced and passes it to fromJSON.

AFAIK the rest of it functions identically, however I have not tested it to a great extent due to content-origin.


## CHANGELOG

### v0.04-0.01 2011-08-06
1. Initial commit.