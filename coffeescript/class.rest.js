(function() {
  var Rest;
  Rest = (function() {
    function Rest() {
      this.url = false;
      this.reset();
    }
    Rest.prototype.reset = function() {
      this.params = {};
      this.path = false;
      return this.method = "GET";
    };
    Rest.prototype.set_method = function(type) {
      type = type.toUpperCase();
      if (type === "GET" || type === "POST" || type === "DELETE" || type === "PUT") {
        return this.method = type;
      } else {
        throw "Unknown HTTP method " + type + " passed to REST::set_method";
      }
    };
    Rest.prototype.add_param = function(key, value) {
      var k, v;
      if (value == null) {
        value = false;
      }
      if (typeof key === "object") {
        for (k in key) {
          v = key[k];
          this.add_param(k, v);
        }
        return;
      }
      return this.params[key] = value;
    };
    Rest.prototype.secure = function(bool) {
      return this.secure = (bool ? true : false);
    };
    Rest.prototype.execute = function(url, callback) {
      var completed, k, pars, req, self, send, v, _ref, _ref2;
      if (!url) {
        url = (this.secure ? 'https' : 'http') + '://' + this.url;
      }
      switch (this.method) {
        case 'POST':
        case 'PUT':
          send = new FormData();
          _ref = this.params;
          for (k in _ref) {
            v = _ref[k];
            send.append(k, v);
          }
          break;
        default:
          pars = [];
          _ref2 = this.params;
          for (k in _ref2) {
            v = _ref2[k];
            pars.push(k + "=" + encodeURI(v));
          }
          if (pars.length) {
            url += "?" + pars.join("&");
          }
          send = null;
      }
      req = new XMLHttpRequest();
      req.open(this.method, url);
      req.onreadystatechange = (function(e) {
        if (req.readyState === 4) {
          if (req.status === 200) {
            return completed(req.responseText, false, 200);
          } else {
            return completed(req.statusText, true, req.status);
          }
        }
      });
      req.send(send);
      self = this;
      return completed = (function(data, error, status) {
        var arg, e1, e2, meth, parser, pdata, result, type;
        if (error == null) {
          error = false;
        }
        if (status == null) {
          status = 200;
        }
        try {
          pdata = data.replace(/\n[^\n"]*\/\/[^\n]*/ig, '');
          result = JSON.parse(pdata);
          type = 'application/json';
        } catch (e) {
          if (window.DOMParser) {
            parser = new DOMParser();
            meth = "parseFromString";
            arg = "text/xml";
          } else {
            parser = new ActiveXObject("Microsoft.XMLDOM");
            parse.async = false;
            meth = "loadXML";
            arg = null;
          }
          try {
            result = parser[meth](data, arg);
            type = "application/xml";
            if (result.getElementsByTagName("parsererror").length > 0) {
              throw "";
            }
          } catch (e) {
            result = data;
            type = "text/plain";
          }
        }
        self.last_request = {
          url: url,
          params: self.params,
          error: error,
          request_type: self.method,
          raw: data,
          status: status,
          content_type: type
        };
        self.path = "aye/bee";
        if ((self.error_check != null) && (e1 = self.resolve_path(result, self.error_check, true))) {
          if (!((self.error_return != null) && (e2 = self.resolve_path(result, self.error_return, true)))) {
            e2 = e1;
          }
          self.last_request.error = e2;
          if (callback) {
            callback(self.last_request.error);
          }
          return self.last_request.error;
        }
        self.last_request.result = self.resolve_path(result, self.path, false);
        if (callback) {
          callback(self.last_request.result);
        }
        return self.last_request.result;
      });
    };
    Rest.prototype.set_path = function(path) {
      return this.path = path;
    };
    Rest.prototype.resolve_path = function(o, path, fatal) {
      var p, _i, _len;
      if (fatal == null) {
        fatal = false;
      }
      if (typeof path === "string") {
        path = path.split(/(\/|\.|-)/ig);
      }
      for (_i = 0, _len = path.length; _i < _len; _i++) {
        p = path[_i];
        if (p !== '/' && p !== '.' && p !== '-') {
          if (o[p] != null) {
            o = o[p];
          } else {
            if (fatal) {
              return false;
            } else {
              return o;
            }
          }
        }
      }
      return o;
    };
    return Rest;
  })();
  this.Rest = Rest;
}).call(this);
