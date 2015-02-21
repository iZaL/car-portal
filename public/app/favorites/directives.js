angular.module('app').directive('favoritePanel', favoritePanel);

favoritePanel.$inject = ['FavoriteService'];

function favoritePanel(FavoriteService) {
    return {
        restrict: 'EA',
        templateUrl: '/app/favorites/panel.html',
        link: function (scope, element) {
            scope.destroy = function (favorite) {
                element.addClass('ng-leave');
                element.fadeOut(1000);
                FavoriteService.destroy(favorite).then(function () {
                    scope.favorite = null;
                });
            };
        }
    }
}

