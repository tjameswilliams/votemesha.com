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
      'get':function(action) {
        var deferred = $q.defer();
        $http({
          method: 'GET',
          url: '/system/'+action
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

  .config(function($routeProvider) {
		$routeProvider
		.when('/confirmation', {
			controller:'global',
		})
		.otherwise({
			redirectTo:'/'
		});
	})

  .factory('Cart', [
    'AJAX',
    '$filter',
    function(AJAX,$filter) {
      var Cart = {
        products: [],
        items: [],
        id:null,
        first_name:null,
        last_name:null,
        email:null,
        address:null,
        city:null,
        state:null,
        _setupCart(cart) {
          var This = this;
          This.products = cart.products;
          This.items = cart.items;
          This.id = cart.id;
          This.total = cart.total;
        },
        getCart: function() {
          var This = this;
          AJAX.get('cart').then(function(cart) {
            This._setupCart(cart);
          });
        },
        addItem: function(item) {
          var This = this;
          if( This.items.length ) {
            var item_ref = $filter('filter')(This.items,{name:item.name}, true)[0];
            if( item_ref ) {
              item.qty++;
              AJAX.post('add_item', item_ref).then(function(cart) {
                This._setupCart(cart);
              });
            }
          }
          if( !item_ref ) {
            item.qty = 1;
            item.cart_id = This.id;
            AJAX.post('add_item', item).then(function(cart) {
              This._setupCart(cart);
            });
          }
        },
        updateItem: function(item) {
          var This = this;
          var item_ref = $filter('filter')(This.items,{name:item.name}, true)[0];
          item_ref.qty = item.qty;
          if( item_ref ) {
            AJAX.post('add_item', item_ref).then(function(cart) {
              This._setupCart(cart);
            });
          }
        },
        removeItem: function(item) {
          var This = this;
          var item_ref = $filter('filter')(This.items,{name:item.name}, true)[0];
          AJAX.post('remove_item', item_ref).then(function(cart) {
            This._setupCart(cart);
          });
        },
        checkout: function() {
          AJAX.post('get_checkout', {id: this.id}).then(function(res) {
            if( res.error ) {
              console.log(res.error);
            } else {
              window.location.href = res.redirect;
            }
          });
        },
        processCheckout: function(token,payerid,cb) {
          var This = this;
          AJAX.post('process_checkout', {
            token: token,
            PayerID: payerid
          }).then(function(res) {
            if( res.success ) {
              window.location.href = window.location.origin+'/#/confirmation';
            } else {
              console.log(res);
            }
          });
        },
        getConfirmation: function(cb) {
          AJAX.post('get_confirmation').then(function(res) {
            cb(res);
          });
        },
        clear: function() {
          window.location.href = window.location.origin;
        }
      };
      Cart.getCart();
      return Cart;
    }
  ])

  .controller('global', [
    "$scope",
    "Cart",
    "$location",
    "$anchorScroll",
    "$mdDialog",
    function($scope,Cart,$location,$anchorScroll,$mdDialog) {
      $scope.cart = Cart;
      var qs = (function(a) {
        if (a == "") return {};
        var b = {};
        for (var i = 0; i < a.length; ++i)
        {
          var p=a[i].split('=', 2);
          if (p.length == 1)
          b[p[0]] = "";
          else
          b[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
        }
        return b;
      })(window.location.search.substr(1).split('&'));
      if( qs.token ) {
        $scope.loading = true;
        Cart.processCheckout(qs.token,qs.PayerID, function(res) {
          if( res.success ) {
            window.location.href = '/#/confirmation';
          } else {
            console.log(res);
          }
        });
      }
      if( $location.$$path == '/confirmation' ) {
        $scope.loading = true;
        // -- TODO get and clear cart
        Cart.getConfirmation(function(res) {
          $scope.loading = false;
          $mdDialog.show({
            controller: ['$scope','Cart','$mdDialog', function($scope,Cart,$mdDialog) {
              $scope.cart = Cart;
              $scope.cancel = function() {
                Cart.clear();
                $mdDialog.cancel();
              };
            }],
            preserveScope: true,
            templateUrl: '/includes/order_confirmation.html',
            parent: angular.element(document.body),
            clickOutsideToClose:true
          })
          .then(function(answer) {

          }, function() {

          });
        });
      }
      $scope.scrollTo = function(id) {
        $location.hash(id);
        $anchorScroll();
      };
    }
  ])

  .controller('products', [
    "$scope",
    "Cart",
    function($scope,Cart) {
      $scope.cart = Cart;
      console.log($scope.cart);
      $scope.checkout = function() {
        $scope.checkoutActive = true;
        Cart.checkout();
      };
    }
  ])

  .controller('admin', [
    "$scope",
    "AJAX",
    function($scope,AJAX) {
      $scope.orders = [];
      AJAX.get('orders').then(function(orders) {
        $scope.orders = orders;
      });
      $scope.updateOrder = function(order) {
        AJAX.post('update_order', order).then(function() {

        });
      };
    }
  ])
