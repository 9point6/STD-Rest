(function() {
  var APIMethod;
  APIMethod = (function() {
    function APIMethod() {
      this.required = [];
      this.optional = [];
    }
    APIMethod.prototype.get_signature = function() {
      var name, sig, _i, _j, _len, _len2, _ref, _ref2;
      sig = {};
      _ref = this.required;
      for (_i = 0, _len = _ref.length; _i < _len; _i++) {
        name = _ref[_i];
        sig[name] = null;
      }
      _ref2 = this.optional;
      for (_j = 0, _len2 = _ref2.length; _j < _len2; _j++) {
        name = _ref2[_j];
        sig[name] = false;
      }
      return sig;
    };
    APIMethod.prototype.set_name = function(name) {
      return this.name = name;
    };
    APIMethod.prototype.set_required = function(required) {
      if (typeof required === "object" && (required.length != null)) {
        return this.required = required;
      } else {
        required = required.replace(/(^ |, | ,| $)/ig, ',');
        if (required.indexOf(",") !== -1) {
          return this.required = required.split(",");
        } else {
          return this.required = [required];
        }
      }
    };
    APIMethod.prototype.set_optional = function(optional) {
      if (typeof optional === "object" && (optional.length != null)) {
        return this.optional = optional;
      } else {
        optional = optional.replace(/(, | ,)/ig, ',').replace(/(^ | $)/ig, '');
        if (optional.indexOf(",") !== -1) {
          return this.optional = optional.split(",");
        } else {
          return this.optional = [optional];
        }
      }
    };
    APIMethod.prototype.set_path = function(path) {
      if (typeof path === "object" && path.length) {
        return this.path = path;
      } else if (path.indexOf("/") !== -1) {
        return this.path = path.split("/", path);
      } else {
        return this.path = (path ? [path] : []);
      }
    };
    APIMethod.prototype.set_authenticated = function(bool) {
      return this.authenticated = (bool ? true : false);
    };
    APIMethod.prototype.set_docs = function(docs) {
      return this.docs = docs;
    };
    APIMethod.prototype.set_request_type = function(type) {
      type = type.toUpperCase().replace(/(^ | $)/ig, '');
      if (type === "GET" || type === "POST" || type === "DELETE" || type === "PUT") {
        return this.request_type = type;
      } else {
        throw "Unknown request type " + type + " passed to method" + this.name;
      }
    };
    APIMethod.prototype.set_validation = function(vals) {
      return this.validation = vals;
    };
    return APIMethod;
  })();
  this.APIMethod = APIMethod;
}).call(this);
