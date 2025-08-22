import { __ } from "@wordpress/i18n";
import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { Content } from "./Content";

const settings = getSetting("azampaymomo_data", {});

const label = decodeEntities(settings.title) || __("AzamPay", "azampay");
const name = decodeEntities(settings.name) || "azampaymomo";
const icon = decodeEntities(settings.icon) || "";

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = (props) => {
  const { PaymentMethodLabel, PaymentMethodIcons } = props.components;

  const icons = [
    {
      id: "azampay-logo",
      src: icon,
      alt: __("Azampay logo", "azampay"),
    },
  ];

  return (
    <>
      <PaymentMethodLabel text={label} />
      <PaymentMethodIcons align="right" icons={icons} className="wc-azampay-logo" />
    </>
  );
};

/**
 * AzamPay payment method config object.
 */
const AzamPay = {
  name: name,
  label: <Label />,
  content: <Content />,
  edit: <></>,
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
  },
};

registerPaymentMethod(AzamPay);
