angular.module('app', ['ngRoute','ngMaterial','ngMessages'])

  .factory('AJAX', ["$http","$q",function($http, $q) {
    return {
      'post':function(action,obj) {
        var deferred = $q.defer();
        $http({
          method: 'POST',
          url: '/system/'+action,
          headers: {
            'Content-type': 'application/json'
          },
          data: JSON.stringify(obj)
        })
        .success(function(data, status, headers, config) {
          deferred.resolve(data);
        });

        return deferred.promise;
      },
      'jsonp':function(action,obj) {
        var deferred = $q.defer();
        if( typeof obj != 'undefined' )
        {
          var urlPostfix = '&'+this.serialize(obj);
        }
        else
        {
          var urlPostfix = '';
        }
        $http.jsonp(action+'?callback=JSON_CALLBACK'+urlPostfix)
        .success(function(data, status, headers, config) {
          deferred.resolve(data);
        });

        return deferred.promise;
      },
      'serialize': function(obj) {
        var str = [];
        for(var p in obj)
        if (obj.hasOwnProperty(p)) {
          str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
        }
        return str.join("&");
      }
    }
  }])

  .controller('global', ["$scope",function($scope) {
    console.log('hello world!');
  }])
