class APIFactory
	constructor: () ->
		@params = {}
		@methods = {}
		@hooked = {}
		
		@rest = new Rest();
	
	# lololol - gets a value or returns a default
	get: (o, k, notok) ->
		return (if o[k]? then o[k] else notok)
	
	# resolves a name to a method.
	m: (name) ->
		name = name.replace(/[^a-z]/ig, '_')

		if @[name]?
			return @[name]

		if @methods[name]?
			return @methods[name]

		o = @
		found = true
		for i, k of name.split('_')
			if o[k]? then (o = o[k]) else (found = false; break)

		return (if found then o else false)

	execute: (method, data) ->
		@method = method

		# extract function as callback (if any)
		callback = null
		for i, v of data
			if !!(v and v.constructor and v.call and v.apply)
				callback = data.splice(i,1)[0]
				break
		
		@call_hook("pre_call", true)
		if method.authenticated then @call_hook("pre_call_auth", true)

		if (method.authenticated and @secure == 'auth_only') or @secure == 'always'
			@rest.secure(true)
		else
			@rest.secure(false)

		sig = @form_signature(method.get_signature(), data)
		ssig = @form_signature(@get_static_fields(method.authenticated), @get_param())
		
		(sig[k] = v for k,v of ssig)

		for key, value of sig
			regex = null
			if @get(method, 'validation') and pattern = @get(method.validation, key)
				regex = (if @get(@validators, pattern) then @validators[pattern].pattern else pattern)

			if not regex
				for name, o of @validators
					if name == key or key in @get(o, 'fields', [])
						regex = o.pattern

			# ensure regexs are wrapped in /../
			if regex and regex.substr(0,1) != "/"
				regex = "/" + regex
			if regex and regex.substr(-1,1) != "/"
				regex = regex + "/"

			if sig[key]? and sig[key] != false and regex and not sig[key].toString().match(regex)
				throw "Validation failed on "+method.name+" > "+key+" where value = "+sig[key]
			
			if sig[key]? and sig[key] is false
				delete sig[key]
			else
				@set_param(key, value)

		@rest.set_method(method.request_type)
		@rest.add_param(sig)
		@rest.set_path(method.path)

		@rest.url = @replace_keys(@url)

		@call_hook("pre_execute", true)
		if method.authenticated then @call_hook("pre_execute_auth", true)
	
		self = @
		@rest.execute(null, (data) ->
			data = self.call_hook("post_execute", true, data)[0]
			if callback then callback(data)
			return data
		)
	
	call_hook: (hook, extra = false) ->
		args = (v for i,v of arguments when i > 0)
		targs = (v for i,v of arguments when i > 0)
		
		hook = args.shift()
		extra = args.shift()

		if extra and @method
			targs[0] += '_' + @method.name.replace(/[^a-z]+/i, '_', @method.name)
			targs[1] = false
			@call_hook(targs)

		hook = "hook_" + hook

		for i, obj of @hooked
			if obj[hook]?
				args = obj[hook](args)
		
		return args

	get_static_fields: (auth = false) ->
		base = @get(@static_fields, 'all', [])
		plus = @get(@static_fields, (if auth then 'auth_only' else 'unauth_only'), [])
		out = {}

		base.concat(plus)
		for i, key of base
			keys = @parse_default(key, null)
			for key, val of keys
				if key.match(/^[0-9]+$/)
					key = val
					val = null
				if key.length
					out[key] = val
		return out
	
	parse_default: (key, def = null) ->
		if key.indexOf("=") < 0
			return [key, def]

		keys = {}
		key = @replace_keys(key)
		
		for i, v of key.split("&")
			k = v.split("=")
			keys[k[0]] = k[1]
		
		return keys


	replace_keys: (str, rk = false, po = false) ->
		args = str.match(/\{([^{}=&,\/]+)\}/gim)

		if not args then return str

		if not po then po = @

		for i,v of args
			if rk and i != rk
				continue
			
			k = v.replace(/(\{|\})/ig, '').split(".")

			o = po
			for kk in k
				if o[kk]?
					o = o[kk]
			
			if typeof o == 'object'
				throw "Replacing keys on "+v+" pointed to an object/array instead of a literal"
			
			if o.match('{') < 0
				o = @replace_keys(o, false, po)

			if rk then return o

			str = str.replace(v, o)
		
		return str

	form_signature: (sig, args) ->
		if args.length? and args.length == 0
			args = []

		for key, value of args
			if typeof sig[key] != 'undefined'
				sig[key] = value
				delete args[key]


		for key, value of sig
			if not sig[key] and args.length > 0
				sig[key] = args.splice(0,1)[0]
				continue

			if key.match(/^[0-9]+$/)
				delete sig[key]
				sig[value] = null
				key = value

			if val = @get_param(key)
				sig[key] = val
			else if sig[key] is null
				throw "Required field "+key+" not set"

		return sig

	get_param: (name = false) ->
		if name
			return (if @params[name]? then @params[name] else false)
		return @params

	set_param: (name, value) ->
		@params[name] = value

	# equiv of private by using = instead of :
	# untested, not a satisfactory solution having to have the auth bits loaded...
	authFactory = (method, data) ->
		if not window[method]?
			return false
		
		return (@authentication = new window[method](@, data))

	add_method: (name, required = [], optional = [], validation = [], path = [], authenticated = false, method = 'get', docs = false) ->
		m = new APIMethod();
		m.set_name(name)
		m.set_required(required)
		m.set_optional(optional)
		m.set_validation(validation)
		m.set_path(path)
		m.set_authenticated(authenticated)
		m.set_request_type(method)
		m.set_docs(docs)

		self = @

		# this makes the names executable.
		mf = (() ->
			# some weird shit going on with arguments here.
			args = []
			(args.push(v) for v in arguments)
			args = args.slice(0, arguments.length)
			self.execute(m, args)
		)

		name = name.replace(/([^a-z])/ig, '_')
		@methods[name] = mf
		@[name] ?= mf

		# this allows the functions to be accesible by a number of routes.
		# eg "store.get_shipping_rate" in the src is available at (at least):
		#   @store_get_shipping_rate
		#   @store.get_shipping_rate
		#	@store_get.shipping_rate
		#	@store_get.shipping.rate
		#	@store.get.shipping_rate
		#	@store_get_shipping.rate
		#	@store.get.shipping.rate
		# In effect, any combination of . and _ between words
		left = @;
		right = name.split("_")
		while right.length > 0
			next = right.splice(0,1)[0]
			if right.length
				left[next+"_"+right.join("_")] ?= mf
				left[next] ?= {}
				left = left[next]
			else
				left[next] = mf

		return m

	fromFile: (file, cb) ->
		self = @
		@rest.execute(file, ((d) ->
			self.fromJSON(d, cb)
		))

	fromJSON: (json, callback) ->
		if not json.service? or not json.methods? or not json.url?
			throw "JSON does not conform to APIFactory spec."

		@service = json.service
		@url = json.url
		methods = json.methods

		if auth = @get(json, "authentication")
			for key, value of auth
				if @authentication = authFactory(key, value)
					@call_hook(pre_load)

		@vars = @get(json, 'vars', [])
		@secure = @get(json, 'secure', 'never')
		@docs = @get(json, 'docs')
		@static_fields = @get(json, 'static_fields')
		@validators = @get(json, 'validators', [])

		for key, value of @validators
			if value.fields? and value.fields.replace?
				fields = value.fields.replace(/(, | ,)/g, ',').replace(/(^ | $)/g, '')
				@validators[key].fields = fields.split(",")


		@rest.error_check = @get(json, 'error_check_path')
		@rest.error_return = @get(json, 'error_return_path')

		for name, method of methods
			name = @get(method, 'name', name)
			required = @get(method, 'required', [])
			optional = @get(method, 'optional', [])
			validation = @get(method, 'validation', [])
			path = @get(method, 'path')
			auth = @get(method, 'authenticated')
			type = @get(method, 'request_type', 'get')

			docs = false
			if @docs and @docs.pattern?
				docs = @replace_keys(@docs.pattern, false, method)

			m = @add_method(name, required, optional, validation, path, auth, type, docs)

			for key, value of method
				if not (key in 'name,required,optional,validation,path,authenticted,request_type'.split(','))
					m[key] = value
		
		@call_hook('post_load')
		
		if callback
			callback(@)
		return @


this.APIFactory = APIFactory