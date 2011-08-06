class APIMethod

	constructor: () ->
		@required = []
		@optional = []

	get_signature: () ->
		sig = {}
		for name in @required
			sig[name] = null
		
		for name in @optional
			sig[name] = false

		return sig

	set_name: (name) ->
		@name = name
	
	set_required: (required) ->
		if typeof(required) == "object" and required.length?
			@required = required
		else
			required = required.replace(/(^ |, | ,| $)/ig, ',')
			if required.indexOf(",") != -1
				@required = required.split(",")
			else
				@required = [required]
	
	set_optional: (optional) ->
		if typeof(optional) == "object" and optional.length?
			@optional = optional
		else
			optional = optional.replace(/(, | ,)/ig, ',').replace(/(^ | $)/ig, '')
			if optional.indexOf(",") != -1
				@optional = optional.split(",")
			else
				@optional = [optional]

	set_path: (path) ->
		if typeof(path) == "object" and path.length
			@path = path
		else if path.indexOf("/") != -1
			@path = path.split("/", path)
		else
			@path = (if path then [path] else [])

	set_authenticated: (bool) ->
		@authenticated = (if bool then true else false)
	
	set_docs: (docs) ->
		@docs = docs

	set_request_type: (type) ->
		type = type.toUpperCase().replace(/(^ | $)/ig, '')
		if type in ["GET", "POST", "DELETE", "PUT"]
			@request_type = type
		else
			throw "Unknown request type "+type+" passed to method"+@name

	set_validation: (vals) ->
		@validation = vals

this.APIMethod = APIMethod