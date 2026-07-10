/*
 * Pause - jQuery plugin
 * http://tobia.github.com/Pause/
 *
 * Copyright (c) 2011-2013 Tobia Conforto
 * Dual licensed under the MIT and GPL licenses.
 * https://raw.github.com/tobia/Pause/master/LICENSE.txt
 */
(function($) {
  $.fn.pause = function() {
    return this.animate({ dummy: 1 }, 0);
  };
  $.fn.resume = function() {
    return this.stop(true, true);
  };
})(jQuery);
