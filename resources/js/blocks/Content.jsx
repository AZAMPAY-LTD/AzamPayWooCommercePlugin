import { __ } from "@wordpress/i18n";
import { getSetting } from "@woocommerce/settings";
import { useEffect, useState, RawHTML } from "@wordpress/element";
import { PaymentNumberField, PaymentPartnersField } from "./InputFields";
import { PHONE_PATTERNS } from "./constants";

const settings = getSetting("azampaymomo_data", {});

const enabled = settings.enabled || true;

const Description = () => {
  return <RawHTML>{settings.description}</RawHTML>;
};

export const Content = (props) => {
  const {
    eventRegistration: { onPaymentSetup },
    emitResponse,
  } = props;

  const [paymentNumber, setPaymentNumber] = useState("");
  const [paymentPartner, setPaymentPartner] = useState("Azampesa");

  useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      if (!enabled) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __("AzamPay is disabled", "azampay-woo"),
        };
      }

      if (!paymentPartner) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __("Please select a payment network", "azampay-woo"),
        };
      }

      const phonePattern = 
        paymentPartner === "Azampesa" ? PHONE_PATTERNS.azampesa : PHONE_PATTERNS.others;

      if (!paymentNumber || !paymentNumber.match(phonePattern)) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __("Please enter a valid phone number that is to be billed.", "azampay-woo"),
        };
      }

      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: {
          paymentMethodData: {
            payment_network: paymentPartner,
            payment_number: paymentNumber,
          },
        },
      };
    });
    // Unsubscribes when this component is unmounted.
    return () => {
      unsubscribe();
    };
  }, [
    emitResponse.responseTypes.ERROR,
    emitResponse.responseTypes.SUCCESS,
    onPaymentSetup,
    paymentNumber,
    paymentPartner,
  ]);

  if (!enabled) {
    return <p>{__("Azampay is disabled", "azampay-woo")}.</p>;
  }

  return (
    <>
      <Description />

      <fieldset id="wc-azampaymomo-form" className="wc-payment-form block-field">
        <PaymentNumberField paymentNumber={paymentNumber} setPaymentNumber={setPaymentNumber} />

        <PaymentPartnersField paymentPartner={paymentPartner} setPaymentPartner={setPaymentPartner} />
      </fieldset>
    </>
  );
};
