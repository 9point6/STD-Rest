class Rest
	constructor: () ->
		@url = false
		@reset()

	set_method: (type) ->
		type = type.toUpperCase()
		if type in ["GET", "POST", "DELETE", "PUT"]
			@method = type
		else
			throw "Unknown HTTP method "+type+" passed to REST::set_method"
	
	add_param: (key, value = false) ->
		if typeof key is "object"
			for k,v of key
				@add_param(k, v)
			return
		
		@params[key] = value

	secure: (bool) ->
		@secure = (if bool then true else false)

	execute: (url, callback) ->
		if not url
			url = (if @secure then 'https' else 'http') + '://' + @url
		
		switch @method
			when 'POST', 'PUT'
				send = new FormData()
				(send.append(k,v) for k,v of @params)
			else
				pars = []
				(pars.push(k + "=" + encodeURI(v)) for k, v of @params)
				if pars.length then url += "?" + pars.join("&")
				send = null

		# need to work out a better bit of code than this.
		req = new XMLHttpRequest()
		req.open(@method, url)
		req.onreadystatechange = ((e) ->
			if req.readyState == 4
				if req.status == 200
					completed(req.responseText, false, 200)
				else
					completed(req.statusText, true, req.status)
		)
		req.send(send)

		self = @
		completed = ((data, error = false, status = 200) ->
			
			# there must be a nicer way to ricochetthrough possible types...
			# as it is, this needs heavy testing.
			try
			# json? strip out single-line comments. Yes doug, I'm sorry.
				pdata = data.replace(/\n[^\n"]*\/\/[^\n]*/ig, '')
				result = JSON.parse(pdata)
				type = 'application/json'
			catch e
			# not json...
				if window.DOMParser # good browsers
					parser = new DOMParser();
					meth = "parseFromString"
					arg = "text/xml"
				else #ie
					parser = new ActiveXObject("Microsoft.XMLDOM")
					parse.async = false
					meth = "loadXML"
					arg = null

				try
				#xml?
					result = parser[meth](data, arg)
					type = "application/xml"

					# parsererror? occurs in chrome but fuck knows elsewhere
					if result.getElementsByTagName("parsererror").length > 0
						throw ""
				catch e
				# not xml, so just give it to them as is
					result = data
					type = "text/plain"

			self.last_request = {
				url: url,
				params: self.params,
				error: error,
				request_type: self.method,
				raw: data,
				status: status,
				content_type: type
			}

			if self.error_check? and e1 = self.resolve_path(result, self.error_check, true)
				if not(self.error_return? and e2 = self.resolve_path(result, self.error_return, true))
					e2 = e1
				
				self.last_request.error = e2
				if callback
					callback(self.last_request.error)
				return self.last_request.error
			
			self.last_request.result = self.resolve_path(result, self.path, false)
			if callback
				callback(self.last_request.result)

			@reset()
			return self.last_request.result
		)

	set_path: (path) ->
		@path = path

	resolve_path: (o, path, fatal = false) ->
		if typeof(path) == "string"
			path = path.split(/(\/|\.|-)/ig)
		
		for p in path when p not in ['/','.','-']
			if o[p]?
				o = o[p]
			else
				return (if fatal then false else o)
		
		return o

	reset: () ->
		@params = {}
		@path = false
		@method = "GET"

this.Rest = Rest