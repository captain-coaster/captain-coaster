(self.webpackChunk=self.webpackChunk||[]).push([[524],{2470:(t,e,o)=>{"use strict";(0,o(3066).E)(o(5490));o(9755),o(6713),o(7198),o(6435),o(3323)},3323:(t,e,o)=>{"use strict";o.r(e)},5490:(t,e,o)=>{var r={"./hello_controller.js":6824,"./notification_controller.js":5925,"./review_actions_controller.js":8152};function n(t){var e=i(t);return o(e)}function i(t){if(!o.o(r,t)){var e=new Error("Cannot find module '"+t+"'");throw e.code="MODULE_NOT_FOUND",e}return r[t]}n.keys=function(){return Object.keys(r)},n.resolve=i,t.exports=n,n.id=5490},5828:(t,e,o)=>{"use strict";o.d(e,{A:()=>r});const r={}},5925:(t,e,o)=>{"use strict";o.r(e),o.d(e,{default:()=>y});o(2675),o(9463),o(2259),o(5700),o(6280),o(6918),o(3792),o(9572),o(4170),o(2892),o(9904),o(4185),o(875),o(287),o(6099),o(825),o(7764),o(2953),o(6031);function r(t){return r="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(t){return typeof t}:function(t){return t&&"function"==typeof Symbol&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},r(t)}function n(t,e){for(var o=0;o<e.length;o++){var r=e[o];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(t,s(r.key),r)}}function i(t,e,o){return e=c(e),function(t,e){if(e&&("object"==r(e)||"function"==typeof e))return e;if(void 0!==e)throw new TypeError("Derived constructors may only return object or undefined");return function(t){if(void 0===t)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return t}(t)}(t,u()?Reflect.construct(e,o||[],c(t).constructor):e.apply(t,o))}function u(){try{var t=!Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){})))}catch(t){}return(u=function(){return!!t})()}function c(t){return c=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(t){return t.__proto__||Object.getPrototypeOf(t)},c(t)}function a(t,e){return a=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(t,e){return t.__proto__=e,t},a(t,e)}function s(t){var e=function(t,e){if("object"!=r(t)||!t)return t;var o=t[Symbol.toPrimitive];if(void 0!==o){var n=o.call(t,e||"default");if("object"!=r(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===e?String:Number)(t)}(t,"string");return"symbol"==r(e)?e:e+""}var l,f,p,y=function(t){function e(){return function(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}(this,e),i(this,e,arguments)}return function(t,e){if("function"!=typeof e&&null!==e)throw new TypeError("Super expression must either be null or a function");t.prototype=Object.create(e&&e.prototype,{constructor:{value:t,writable:!0,configurable:!0}}),Object.defineProperty(t,"prototype",{writable:!1}),e&&a(t,e)}(e,t),o=e,r=[{key:"connect",value:function(){console.log("Notification controller connected",this.element)}},{key:"show",value:function(t){var e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:"info",o=arguments.length>2&&void 0!==arguments[2]?arguments[2]:3e3,r=document.createElement("div");r.className="alert alert-".concat(e," alert-styled-left alert-arrow-left alert-bordered"),r.style.position="fixed",r.style.top="20px",r.style.right="20px",r.style.maxWidth="400px",r.style.zIndex="9999",r.style.boxShadow="0 4px 8px rgba(0,0,0,0.2)",r.innerHTML='\n            <button type="button" class="close" data-dismiss="alert"><span>×</span><span class="sr-only">Close</span></button>\n            '.concat(t,"\n        "),document.body.appendChild(r);var n=r.querySelector(".close");n&&n.addEventListener("click",(function(){r.remove()})),r.style.opacity="0",r.style.transition="opacity 0.3s ease-in-out",setTimeout((function(){r.style.opacity="1"}),10),setTimeout((function(){r.style.opacity="0",setTimeout((function(){return r.remove()}),300)}),o)}},{key:"showSuccess",value:function(t){this.show(t,"success")}},{key:"showInfo",value:function(t){this.show(t,"info")}},{key:"showWarning",value:function(t){this.show(t,"warning")}},{key:"showDanger",value:function(t){this.show(t,"danger")}}],r&&n(o.prototype,r),u&&n(o,u),Object.defineProperty(o,"prototype",{writable:!1}),o;var o,r,u}(o(2891).xI);l=y,p=["container"],(f=s(f="targets"))in l?Object.defineProperty(l,f,{value:p,enumerable:!0,configurable:!0,writable:!0}):l[f]=p},6435:(t,e,o)=>{"use strict";o.r(e)},6713:(t,e,o)=>{"use strict";o.r(e)},6824:(t,e,o)=>{"use strict";o.r(e),o.d(e,{default:()=>l});o(2675),o(9463),o(2259),o(5700),o(6280),o(6918),o(3792),o(9572),o(4170),o(2892),o(9904),o(4185),o(875),o(287),o(6099),o(825),o(7764),o(2953);function r(t){return r="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(t){return typeof t}:function(t){return t&&"function"==typeof Symbol&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},r(t)}function n(t,e){for(var o=0;o<e.length;o++){var r=e[o];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(t,i(r.key),r)}}function i(t){var e=function(t,e){if("object"!=r(t)||!t)return t;var o=t[Symbol.toPrimitive];if(void 0!==o){var n=o.call(t,e||"default");if("object"!=r(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===e?String:Number)(t)}(t,"string");return"symbol"==r(e)?e:e+""}function u(t,e,o){return e=a(e),function(t,e){if(e&&("object"==r(e)||"function"==typeof e))return e;if(void 0!==e)throw new TypeError("Derived constructors may only return object or undefined");return function(t){if(void 0===t)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return t}(t)}(t,c()?Reflect.construct(e,o||[],a(t).constructor):e.apply(t,o))}function c(){try{var t=!Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){})))}catch(t){}return(c=function(){return!!t})()}function a(t){return a=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(t){return t.__proto__||Object.getPrototypeOf(t)},a(t)}function s(t,e){return s=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(t,e){return t.__proto__=e,t},s(t,e)}var l=function(t){function e(){return function(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}(this,e),u(this,e,arguments)}return function(t,e){if("function"!=typeof e&&null!==e)throw new TypeError("Super expression must either be null or a function");t.prototype=Object.create(e&&e.prototype,{constructor:{value:t,writable:!0,configurable:!0}}),Object.defineProperty(t,"prototype",{writable:!1}),e&&s(t,e)}(e,t),o=e,(r=[{key:"connect",value:function(){this.element.textContent="Hello Stimulus! Edit me in assets/controllers/hello_controller.js"}}])&&n(o.prototype,r),i&&n(o,i),Object.defineProperty(o,"prototype",{writable:!1}),o;var o,r,i}(o(2891).xI)},7198:(t,e,o)=>{"use strict";o.r(e)},8152:(t,e,o)=>{"use strict";o.r(e),o.d(e,{default:()=>y});o(2675),o(9463),o(2259),o(5700),o(6280),o(6918),o(8706),o(3792),o(9572),o(4170),o(2892),o(9904),o(4185),o(875),o(287),o(6099),o(3362),o(825),o(7764),o(2953);var r=o(2891),n=o(6764);function i(t){return i="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(t){return typeof t}:function(t){return t&&"function"==typeof Symbol&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},i(t)}function u(t,e){for(var o=0;o<e.length;o++){var r=e[o];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(t,p(r.key),r)}}function c(t,e,o){return e=s(e),function(t,e){if(e&&("object"==i(e)||"function"==typeof e))return e;if(void 0!==e)throw new TypeError("Derived constructors may only return object or undefined");return function(t){if(void 0===t)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return t}(t)}(t,a()?Reflect.construct(e,o||[],s(t).constructor):e.apply(t,o))}function a(){try{var t=!Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){})))}catch(t){}return(a=function(){return!!t})()}function s(t){return s=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(t){return t.__proto__||Object.getPrototypeOf(t)},s(t)}function l(t,e){return l=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(t,e){return t.__proto__=e,t},l(t,e)}function f(t,e,o){return(e=p(e))in t?Object.defineProperty(t,e,{value:o,enumerable:!0,configurable:!0,writable:!0}):t[e]=o,t}function p(t){var e=function(t,e){if("object"!=i(t)||!t)return t;var o=t[Symbol.toPrimitive];if(void 0!==o){var r=o.call(t,e||"default");if("object"!=i(r))return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===e?String:Number)(t)}(t,"string");return"symbol"==i(e)?e:e+""}var y=function(t){function e(){return function(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}(this,e),c(this,e,arguments)}return function(t,e){if("function"!=typeof e&&null!==e)throw new TypeError("Super expression must either be null or a function");t.prototype=Object.create(e&&e.prototype,{constructor:{value:t,writable:!0,configurable:!0}}),Object.defineProperty(t,"prototype",{writable:!1}),e&&l(t,e)}(e,t),o=e,r=[{key:"connect",value:function(){console.log("Review actions controller connected",this.element),console.log("Review ID:",this.idValue),console.log("Upvote URL:",this.upvoteUrlValue),console.log("Report URL:",this.reportUrlValue),console.log("Has upvote button target:",this.hasUpvoteButtonTarget),console.log("Has report button target:",this.hasReportButtonTarget),console.log("Has report modal target:",this.hasReportModalTarget),this.hasUpvoteButtonTarget&&this.upvotedValue&&this._updateUpvoteButtonState()}},{key:"upvote",value:function(t){var e=this;console.log("Upvote action triggered",t),t.preventDefault(),this.hasUpvoteUrlValue?fetch(this.upvoteUrlValue,{method:"POST",headers:{"X-Requested-With":"XMLHttpRequest"}}).then((function(t){return t.json()})).then((function(t){t.success&&(e.hasUpvoteCountTarget&&(e.upvoteCountTarget.textContent=t.upvoteCount),e.upvotedValue="added"===t.action,e._updateUpvoteButtonState())})).catch((function(t){console.error("Error toggling upvote:",t)})):console.error("Upvote URL not provided")}},{key:"openReportModal",value:function(t){console.log("Open report modal action triggered",t),t.preventDefault(),this.hasReportModalTarget&&$(this.reportModalTarget).modal("show")}},{key:"submitReport",value:function(t){var e=this;if(console.log("Submit report action triggered",t),t.preventDefault(),this.hasReportUrlValue){var o=t.currentTarget,r=new FormData(o);fetch(this.reportUrlValue,{method:"POST",body:r,headers:{"X-Requested-With":"XMLHttpRequest"}}).then((function(t){return t.json()})).then((function(t){t.success?(e.hasReportModalTarget&&$(e.reportModalTarget).modal("hide"),e.hasReportButtonTarget&&(e.reportButtonTarget.disabled=!0,e.reportButtonTarget.classList.add("disabled")),e._showNotification((0,n.pwD)(n.qu6),"success")):e._showNotification(t.message||(0,n.pwD)(n.Zef),"danger")})).catch((function(t){console.error("Error submitting report:",t),e._showNotification((0,n.pwD)(n.Zef),"danger")}))}else console.error("Report URL not provided")}},{key:"_updateUpvoteButtonState",value:function(){if(this.hasUpvoteButtonTarget)if(this.upvotedValue){this.upvoteButtonTarget.classList.add("active"),this.upvoteButtonTarget.setAttribute("title",(0,n.pwD)(n.qvC));var t=this.upvoteButtonTarget.querySelector("i");t&&t.classList.add("text-primary")}else{this.upvoteButtonTarget.classList.remove("active"),this.upvoteButtonTarget.setAttribute("title",(0,n.pwD)(n.BZm));var e=this.upvoteButtonTarget.querySelector("i");e&&e.classList.remove("text-primary")}}},{key:"toggleReview",value:function(t){if(t.preventDefault(),this.hasReviewContentTarget){var e=this.reviewContentTarget,o=e.querySelector(".review-short"),r=e.querySelector(".review-full");"none"!==o.style.display?(o.style.display="none",r.style.display="block"):(o.style.display="block",r.style.display="none")}}},{key:"_showNotification",value:function(t){var e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:"info",o=this.application.getControllerForElementAndIdentifier(document.getElementById("notifications"),"notification");if(o)switch(e){case"success":o.showSuccess(t);break;case"warning":o.showWarning(t);break;case"danger":o.showDanger(t);break;default:o.showInfo(t)}else console.log("Notification (".concat(e,"): ").concat(t))}}],r&&u(o.prototype,r),i&&u(o,i),Object.defineProperty(o,"prototype",{writable:!1}),o;var o,r,i}(r.xI);f(y,"targets",["upvoteButton","upvoteCount","reportButton","reportModal","reviewContent","expandButton","collapseButton"]),f(y,"values",{id:Number,upvoted:Boolean,upvoteUrl:String,reportUrl:String})},9755:(t,e,o)=>{"use strict";o.r(e)}},t=>{t.O(0,[199,153,764],(()=>{return e=2470,t(t.s=e);var e}));t.O()}]);