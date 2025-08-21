/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/js/blocks/Content.jsx":
/*!*****************************************!*\
  !*** ./resources/js/blocks/Content.jsx ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Content: () => (/* binding */ Content)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _InputFields__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./InputFields */ "./resources/js/blocks/InputFields.jsx");
/* harmony import */ var _constants__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./constants */ "./resources/js/blocks/constants.js");






const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__.getSetting)("azampaymomo_data", {});
const enabled = settings.enabled || true;
const Description = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.RawHTML, null, settings.description);
};
const Content = props => {
  const {
    eventRegistration: {
      onPaymentSetup
    },
    emitResponse
  } = props;
  const [paymentNumber, setPaymentNumber] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)("");
  const [paymentPartner, setPaymentPartner] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)("Azampesa");
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    const unsubscribe = onPaymentSetup(async () => {
      if (!enabled) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("AzamPay is disabled", "azampay-woo")
        };
      }
      if (!paymentPartner) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Please select a payment network", "azampay-woo")
        };
      }
      const phonePattern = paymentPartner === "Azampesa" ? _constants__WEBPACK_IMPORTED_MODULE_5__.PHONE_PATTERNS.azampesa : _constants__WEBPACK_IMPORTED_MODULE_5__.PHONE_PATTERNS.others;
      if (!paymentNumber || !paymentNumber.match(phonePattern)) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Please enter a valid phone number that is to be billed.", "azampay-woo")
        };
      }
      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: {
          paymentMethodData: {
            payment_network: paymentPartner,
            payment_number: paymentNumber
          }
        }
      };
    });
    // Unsubscribes when this component is unmounted.
    return () => {
      unsubscribe();
    };
  }, [emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS, onPaymentSetup, paymentNumber, paymentPartner]);
  if (!enabled) {
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Azampay is disabled", "azampay-woo"), ".");
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Description, null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("fieldset", {
    id: "wc-azampaymomo-form",
    className: "wc-payment-form block-field"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_InputFields__WEBPACK_IMPORTED_MODULE_4__.PaymentNumberField, {
    paymentNumber: paymentNumber,
    setPaymentNumber: setPaymentNumber
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_InputFields__WEBPACK_IMPORTED_MODULE_4__.PaymentPartnersField, {
    paymentPartner: paymentPartner,
    setPaymentPartner: setPaymentPartner
  })));
};

/***/ }),

/***/ "./resources/js/blocks/InputFields.jsx":
/*!*********************************************!*\
  !*** ./resources/js/blocks/InputFields.jsx ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PaymentNumberField: () => (/* binding */ PaymentNumberField),
/* harmony export */   PaymentPartnersField: () => (/* binding */ PaymentPartnersField)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__);



const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__.getSetting)("azampaymomo_data", {});
const PaymentNumberField = props => {
  const {
    paymentNumber,
    setPaymentNumber
  } = props;
  if (paymentNumber === undefined || setPaymentNumber === undefined) {
    throw new Error((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("paymentNumber and setPaymentNumber are required as props.", "azampay-woo"));
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    id: "payment_number_field",
    name: "payment_number",
    className: "form-row form-row-wide payment-number-field mt-0",
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Enter mobile phone number", "azampay-woo"),
    type: "text",
    role: "presentation",
    required: true,
    value: paymentNumber,
    onChange: e => setPaymentNumber(e.target.value)
  });
};
const PaymentPartnersField = props => {
  const {
    paymentPartner,
    setPaymentPartner
  } = props;
  if (paymentPartner === undefined || setPaymentPartner === undefined) {
    throw new Error((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("paymentPartner and setPaymentPartner are required as props.", "azampay-woo"));
  }
  if (!settings?.partners?.data) return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("No payment partners available.", "azampay-woo"));
  const {
    partners: {
      data,
      icons
    }
  } = settings;
  const {
    src: azampesaSrc,
    alt: azampesaAlt
  } = icons["Azampesa"] || {
    src: "",
    alt: ""
  };
  const onPartnerChange = changeEvent => {
    setPaymentPartner(changeEvent.target.value);
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    class: "form-row form-row-wide azampesa-label-container"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    class: "azampesa-container",
    style: {
      marginBlock: "1em"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    id: "azampesa-radio-btn",
    type: "radio",
    name: "payment_network",
    value: data["Azampesa"] || "azampesa",
    checked: paymentPartner.toLowerCase() === (data["Azampesa"] || "azampesa").toLowerCase(),
    onChange: onPartnerChange
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    class: "azampesa-right-block",
    style: {}
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Pay with AzamPesa", "azampay-woo")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    class: "azampesa-img",
    src: azampesaSrc,
    alt: azampesaAlt
  })))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    class: "form-row form-row-wide content radio-btn-container"
  }, Object.entries(data).map(([name, value]) => {
    if (name === "Azampesa") return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null);
    const {
      src,
      alt
    } = icons[name] || {
      src: "",
      alt: ""
    };
    const checked = paymentPartner.toLowerCase() === value.toLowerCase();
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
      class: "other-partners-radio-btn",
      type: "radio",
      name: "payment_network",
      value: value,
      checked: checked,
      onChange: onPartnerChange
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
      class: "other-partner-img",
      src: src,
      alt: alt
    }));
  })));
};

/***/ }),

/***/ "./resources/js/blocks/constants.js":
/*!******************************************!*\
  !*** ./resources/js/blocks/constants.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PHONE_PATTERNS: () => (/* binding */ PHONE_PATTERNS)
/* harmony export */ });
const PHONE_PATTERNS = {
  azampesa: /^(0|1|255|\\+255)?(6[1-9]|7[1-8])([0-9]{7})$/,
  others: /^(0|255|\\+255)?(6[1-9]|7[1-8])([0-9]{7})$/
};

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "@woocommerce/blocks-registry":
/*!******************************************!*\
  !*** external ["wc","wcBlocksRegistry"] ***!
  \******************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcBlocksRegistry"];

/***/ }),

/***/ "@woocommerce/settings":
/*!************************************!*\
  !*** external ["wc","wcSettings"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcSettings"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/html-entities":
/*!**************************************!*\
  !*** external ["wp","htmlEntities"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wp"]["htmlEntities"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!**************************************!*\
  !*** ./resources/js/blocks/index.js ***!
  \**************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @woocommerce/blocks-registry */ "@woocommerce/blocks-registry");
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _Content__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./Content */ "./resources/js/blocks/Content.jsx");






const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__.getSetting)("azampaymomo_data", {});
const label = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(settings.title) || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("AzamPay", "azampay-woo");
const name = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(settings.name) || "azampaymomo";
const icon = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(settings.icon) || "";

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = props => {
  const {
    PaymentMethodLabel,
    PaymentMethodIcons
  } = props.components;
  const icons = [{
    id: "azampay-logo",
    src: icon,
    alt: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Azampay logo", "azampay-woo")
  }];
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(PaymentMethodLabel, {
    text: label
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(PaymentMethodIcons, {
    align: "right",
    icons: icons,
    className: "wc-azampay-logo"
  }));
};

/**
 * AzamPay payment method config object.
 */
const AzamPay = {
  name: name,
  label: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Label, null),
  content: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_Content__WEBPACK_IMPORTED_MODULE_5__.Content, null),
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports
  }
};
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_2__.registerPaymentMethod)(AzamPay);
})();

/******/ })()
;
//# sourceMappingURL=wc-azampay-blocks.js.map