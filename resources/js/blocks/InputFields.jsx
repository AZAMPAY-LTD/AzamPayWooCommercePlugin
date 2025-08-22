import { __ } from "@wordpress/i18n";
import { getSetting } from "@woocommerce/settings";

const settings = getSetting("azampaymomo_data", {});

export const PaymentNumberField = (props) => {
  const { paymentNumber, setPaymentNumber } = props;

  if (paymentNumber === undefined || setPaymentNumber === undefined) {
    throw new Error(__("paymentNumber and setPaymentNumber are required as props.", "azampay"));
  }

  return (
    <input
      id="payment_number_field"
      name="payment_number"
      className="form-row form-row-wide payment-number-field mt-0"
      placeholder={__("Enter mobile phone number", "azampay")}
      type="text"
      role="presentation"
      required
      value={paymentNumber}
      onChange={(e) => setPaymentNumber(e.target.value)}
    />
  );
};

export const PaymentPartnersField = (props) => {
  const { paymentPartner, setPaymentPartner } = props;

  if (paymentPartner === undefined || setPaymentPartner === undefined) {
    throw new Error(__("paymentPartner and setPaymentPartner are required as props.", "azampay"));
  }

  if (!settings?.partners?.data) return <p>{__("No payment partners available.", "azampay")}</p>;

  const {
    partners: { data, icons },
  } = settings;

  const { src: azampesaSrc, alt: azampesaAlt } = icons["Azampesa"] || { src: "", alt: "" };

  const onPartnerChange = (changeEvent) => {
    setPaymentPartner(changeEvent.target.value);
  };

  return (
    <>
      {/* Azampesa Content */}
      <div class="form-row form-row-wide azampesa-label-container">
        <label
          class="azampesa-container"
          style={{
            marginBlock: "1em",
          }}>
          <input
            id="azampesa-radio-btn"
            type="radio"
            name="payment_network"
            value={data["Azampesa"] || "azampesa"}
            checked={paymentPartner.toLowerCase() === (data["Azampesa"] || "azampesa").toLowerCase()}
            onChange={onPartnerChange}
          />
          <div class="azampesa-right-block" style={{}}>
            <p>{__("Pay with AzamPesa", "azampay")}</p>
            <img class="azampesa-img" src={azampesaSrc} alt={azampesaAlt} />
          </div>
        </label>
      </div>

      <div class="form-row form-row-wide content radio-btn-container">
        {Object.entries(data).map(([name, value]) => {
          if (name === "Azampesa") return <></>;

          const { src, alt } = icons[name] || { src: "", alt: "" };

          const checked = paymentPartner.toLowerCase() === value.toLowerCase();

          return (
            <label>
              <input
                class="other-partners-radio-btn"
                type="radio"
                name="payment_network"
                value={value}
                checked={checked}
                onChange={onPartnerChange}
              />
              <img class="other-partner-img" src={src} alt={alt} />
            </label>
          );
        })}
      </div>
    </>
  );
};
