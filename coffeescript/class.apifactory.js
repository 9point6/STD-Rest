(function() {
  var APIFactory;
  var __indexOf = Array.prototype.indexOf || function(item) {
    for (var i = 0, l = this.length; i < l; i++) {
      if (this[i] === item) return i;
    }
    return -1;
  };
  APIFactory = (function() {
    var authFactory;
    function APIFactory() {
      this.params = {};
      this.methods = {};
      this.rest = new Rest();
    }
    APIFactory.prototype.get = function(o, k, notok) {
      if (o[k] != null) {
        return o[k];
      } else {
        return notok;
      }
    };
    APIFactory.prototype.m = function(name) {
      var found, i, k, o, _ref;
      name = name.replace(/[^a-z]/ig, '_');
      if (this[name] != null) {
        return this[name];
      }
      if (this.methods[name] != null) {
        return this.methods[name];
      }
      o = this;
      found = true;
      _ref = name.split('_');
      for (i in _ref) {
        k = _ref[i];
        if (o[k] != null) {
          o = o[k];
        } else {
          found = false;
          break;
        }
      }
      if (found) {
        return o;
      } else {
        return false;
      }
    };
    APIFactory.prototype.execute = function(method, data) {
      var callback, i, k, key, name, o, pattern, regex, self, sig, ssig, v, value, _ref;
      this.method = method;
      callback = null;
      for (i in data) {
        v = data[i];
        if (!!(v && v.constructor && v.call && v.apply)) {
          callback = data.splice(i, 1)[0];
          break;
        }
      }
      this.call_hook("pre_call");
      if (method.authenticated) {
        this.call_hook("pre_call_auth");
      }
      if ((method.authenticated && this.secure === 'auth_only') || this.secure === 'always') {
        this.rest.secure(true);
      } else {
        this.rest.secure(false);
      }
      sig = this.form_signature(method.get_signature(), data);
      ssig = this.form_signature(this.get_static_fields(method.authenticated), this.get_param());
      for (k in ssig) {
        v = ssig[k];
        sig[k] = v;
      }
      for (key in sig) {
        value = sig[key];
        regex = null;
        if (this.get(method, 'validation') && (pattern = this.get(method.validation, key))) {
          regex = (this.get(this.validators, pattern) ? this.validators[pattern].pattern : pattern);
        }
        if (!regex) {
          _ref = this.validators;
          for (name in _ref) {
            o = _ref[name];
            if (name === key || __indexOf.call(this.get(o, 'fields', []), key) >= 0) {
              regex = o.pattern;
            }
          }
        }
        if (regex && regex.substr(0, 1) !== "/") {
          regex = "/" + regex;
        }
        if (regex && regex.substr(-1, 1) !== "/") {
          regex = regex + "/";
        }
        if ((sig[key] != null) && sig[key] !== false && regex && !sig[key].toString().match(regex)) {
          throw "Validation failed on " + method.name + " > " + key + " where value = " + sig[key];
        }
        if ((sig[key] != null) && sig[key] === false) {
          delete sig[key];
        }
      }
      this.rest.set_method(method.request_type);
      this.rest.add_param(sig);
      this.rest.set_path(method.path);
      this.rest.url = this.replace_keys(this.url);
      this.call_hook("pre_execute");
      if (method.authenticated) {
        this.call_hook("pre_execute_auth");
      }
      self = this;
      return this.rest.execute(null, function(data) {
        data = self.call_hook("post_execute", data)[0];
        if (callback) {
          callback(data);
        }
        return data;
      });
    };
    APIFactory.prototype.call_hook = function(hook) {
      var i, v;
      arguments = (function() {
        var _results;
        _results = [];
        for (i in arguments) {
          v = arguments[i];
          if (i > 0) {
            _results.push(v);
          }
        }
        return _results;
      }).apply(this, arguments);
      hook = "hook_" + hook;
      if (this.authentication && (this.authentication[hook] != null)) {
        return this.authentication[hook].apply(this, arguments);
      } else {
        return arguments;
      }
    };
    APIFactory.prototype.get_static_fields = function(auth) {
      var base, i, key, keys, out, plus, val;
      if (auth == null) {
        auth = false;
      }
      base = this.get(this.static_fields, 'all', []);
      plus = this.get(this.static_fields, (auth ? 'auth_only' : 'unauth_only'), []);
      base.concat(plus);
      out = {};
      for (i in base) {
        key = base[i];
        keys = this.parse_default(key, null);
        for (key in keys) {
          val = keys[key];
          if (key.match(/^[0-9]+$/)) {
            key = val;
            val = null;
          }
          if (key.length) {
            out[key] = val;
          }
        }
      }
      return out;
    };
    APIFactory.prototype.parse_default = function(key, def) {
      var i, k, keys, v, _ref;
      if (def == null) {
        def = null;
      }
      if (key.indexOf("=") < 0) {
        return [key, def];
      }
      keys = {};
      key = this.replace_keys(key);
      _ref = key.split("&");
      for (i in _ref) {
        v = _ref[i];
        k = v.split("=");
        keys[k[0]] = k[1];
      }
      return keys;
    };
    APIFactory.prototype.replace_keys = function(str, rk, po) {
      var args, i, k, kk, o, v, _i, _len;
      if (rk == null) {
        rk = false;
      }
      if (po == null) {
        po = false;
      }
      args = str.match(/\{([^{}=&,\/]+)\}/gim);
      if (!args) {
        return str;
      }
      if (!po) {
        po = this;
      }
      for (i in args) {
        v = args[i];
        if (rk && i !== rk) {
          continue;
        }
        k = v.replace(/(\{|\})/ig, '').split(".");
        o = po;
        for (_i = 0, _len = k.length; _i < _len; _i++) {
          kk = k[_i];
          if (o[kk] != null) {
            o = o[kk];
          }
        }
        if (typeof o === 'object') {
          throw "Replacing keys on " + v + " pointed to an object/array instead of a literal";
        }
        if (rk) {
          return o;
        }
        str = str.replace(v, o);
      }
      return str;
    };
    APIFactory.prototype.form_signature = function(sig, args) {
      var key, val, value;
      if ((args.length != null) && args.length === 0) {
        args = [];
      }
      for (key in args) {
        value = args[key];
        if (sig[key] != null) {
          sig[key] = value;
          delete args[key];
        }
      }
      for (key in sig) {
        value = sig[key];
        if (!sig[key] && args.length > 0) {
          sig[key] = args.splice(0, 1)[0];
          continue;
        }
        if (key.match(/^[0-9]+$/)) {
          delete sig[key];
          sig[value] = null;
          key = value;
        }
        if (val = this.get_param(key)) {
          sig[key] = val;
        } else if (sig[key] === null) {
          throw "Required field " + key + " not set";
        }
      }
      return sig;
    };
    APIFactory.prototype.get_param = function(name) {
      if (name == null) {
        name = false;
      }
      if (name) {
        if (this.params[name] != null) {
          return this.params[name];
        } else {
          return false;
        }
      }
      return this.params;
    };
    APIFactory.prototype.set_param = function(name, value) {
      return this.params[name] = value;
    };
    authFactory = function(method, data) {
      if (!(window[method] != null)) {
        return false;
      }
      return this.authentication = new window[method](this, data);
    };
    APIFactory.prototype.add_method = function(name, required, optional, validation, path, authenticated, method, docs) {
      var left, m, mf, next, right, self, _name, _ref, _ref2, _ref3;
      if (required == null) {
        required = [];
      }
      if (optional == null) {
        optional = [];
      }
      if (validation == null) {
        validation = [];
      }
      if (path == null) {
        path = [];
      }
      if (authenticated == null) {
        authenticated = false;
      }
      if (method == null) {
        method = 'get';
      }
      if (docs == null) {
        docs = false;
      }
      m = new APIMethod();
      m.set_name(name);
      m.set_required(required);
      m.set_optional(optional);
      m.set_validation(validation);
      m.set_path(path);
      m.set_authenticated(authenticated);
      m.set_request_type(method);
      m.set_docs(docs);
      self = this;
      mf = (function() {
        var args, v, _i, _len;
        args = [];
        for (_i = 0, _len = arguments.length; _i < _len; _i++) {
          v = arguments[_i];
          args.push(v);
        }
        args = args.slice(0, arguments.length);
        return self.execute(m, args);
      });
      name = name.replace(/([^a-z])/ig, '_');
      this.methods[name] = mf;
            if ((_ref = this[name]) != null) {
        _ref;
      } else {
        this[name] = mf;
      };
      left = this;
      right = name.split("_");
      while (right.length > 0) {
        next = right.splice(0, 1)[0];
        if (right.length) {
                    if ((_ref2 = left[_name = next + "_" + right.join("_")]) != null) {
            _ref2;
          } else {
            left[_name] = mf;
          };
                    if ((_ref3 = left[next]) != null) {
            _ref3;
          } else {
            left[next] = {};
          };
          left = left[next];
        } else {
          left[next] = mf;
        }
      }
      return m;
    };
    APIFactory.prototype.fromFile = function(file, cb) {
      var self;
      self = this;
      return this.rest.execute(file, (function(d) {
        return self.fromJSON(d, cb);
      }));
    };
    APIFactory.prototype.fromJSON = function(json, callback) {
      var auth, docs, fields, key, m, method, methods, name, optional, path, required, type, validation, value, _ref;
      if (!(json.service != null) || !(json.methods != null) || !(json.url != null)) {
        throw "JSON does not conform to APIFactory spec.";
      }
      this.service = json.service;
      this.url = json.url;
      methods = json.methods;
      if (auth = this.get(json, "authentication")) {
        for (key in auth) {
          value = auth[key];
          if (this.authentication = authFactory(key, value)) {
            this.call_hook(pre_load);
          }
        }
      }
      this.vars = this.get(json, 'vars', []);
      this.secure = this.get(json, 'secure', 'never');
      this.docs = this.get(json, 'docs');
      this.static_fields = this.get(json, 'static_fields');
      this.validators = this.get(json, 'validators', []);
      _ref = this.validators;
      for (key in _ref) {
        value = _ref[key];
        if ((value.fields != null) && (value.fields.replace != null)) {
          fields = value.fields.replace(/(, | ,)/g, ',').replace(/(^ | $)/g, '');
          this.validators[key].fields = fields.split(",");
        }
      }
      this.rest.error_check = this.get(json, 'error_check_path');
      this.rest.error_return = this.get(json, 'error_return_path');
      for (name in methods) {
        method = methods[name];
        name = this.get(method, 'name', name);
        required = this.get(method, 'required', []);
        optional = this.get(method, 'optional', []);
        validation = this.get(method, 'validation', []);
        path = this.get(method, 'path');
        auth = this.get(method, 'authenticated');
        type = this.get(method, 'request_type', 'get');
        docs = false;
        if (this.docs && (this.docs.pattern != null)) {
          docs = this.replace_keys(this.docs.pattern, false, method);
        }
        m = this.add_method(name, required, optional, validation, path, auth, type, docs);
        for (key in method) {
          value = method[key];
          if (!(__indexOf.call('name,required,optional,validation,path,authenticted,request_type'.split(','), key) >= 0)) {
            m[key] = value;
          }
        }
      }
      this.call_hook('post_load');
      if (callback) {
        callback(this);
      }
      return this;
    };
    return APIFactory;
  })();
  this.APIFactory = APIFactory;
}).call(this);
