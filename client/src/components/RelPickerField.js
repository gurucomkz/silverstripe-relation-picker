import React, { Component } from 'react';
import Select from 'react-select';
import fetch from 'isomorphic-fetch';
import fieldHolder from 'components/FieldHolder/FieldHolder';
import url from 'url';
import debounce from 'debounce-promise';
import PropTypes from 'prop-types';

class RelPickerField extends Component {
  constructor(props) {
    super(props);

    if (!this.isControlled()) {
      this.state = {
        value: props.value,
      };
    }

    this.handleChange = this.handleChange.bind(this);
    this.handleOnBlur = this.handleOnBlur.bind(this);
    this.getOptions = this.getOptions.bind(this);
    this.fetchOptions = debounce(this.fetchOptions, 500);
  }

  /**
   * Get the options that should be shown to the user for this tagfield, optionally filtering by the
   * given string input
   *
   * @param {string} input
   * @return {Promise<Array<Object>>|Promise<{options: Array<Object>}>}
   */
  getOptions(input) {
    const { lazyLoad, options } = this.props;

    if (!lazyLoad) {
      return Promise.resolve({ options });
    }

    if (!input) {
      return Promise.resolve({ options: [] });
    }

    return this.fetchOptions(input);
  }

  /**
   * Handle a change, either calling the change handler provided (if controlled) or updating
   * internal state of this component
   *
   * @param {string} value
   */
  handleChange(value) {
    if (this.isControlled()) {
      this.props.onChange(value);
      return;
    }

    this.setState({
      value
    });
    const input = document.querySelector('input[name="SubscriberID"]');
    if (input) {
      const changeWatcher = function _changeWatcher() {
        input.dispatchEvent(new Event('change'));
      };
      window.setTimeout(changeWatcher, 100);
    }
  }

  /**
   * Determine if this input should be "controlled" or not. Controlled inputs should rely on their
   * value coming from props and a change handler provided to update the state stored elsewhere.
   * This is specifically the case for use with `redux-form`.
   *
   * @return {boolean}
   */
  isControlled() {
    return typeof this.props.onChange === 'function';
  }

  /**
   * Required to prevent RelPickerField being cleared on blur
   *
   * @link https://github.com/JedWatson/react-select/issues/805
   */
  handleOnBlur() {

  }

  /**
   * Initiate a request to fetch options, optionally using the given string as a filter.
   *
   * @param {string} input
   * @return {Promise<{options: Array<Object>}>}
   */
  fetchOptions(input) {
    const { optionUrl, labelKey, valueKey } = this.props;
    const fetchURL = url.parse(optionUrl, true);
    fetchURL.query.term = input;
    if (fetchURL.search) delete fetchURL.search;

    return fetch(url.format(fetchURL), { credentials: 'same-origin' })
      .then((response) => response.json())
      .then((json) => ({
        options: json.items.map(item => ({
          [labelKey]: item.Title,
          [valueKey]: item.Value,
        }))
      }));
  }

  renderCreateNew() {
    const { createNewUrl } = this.props;
    if (!createNewUrl) {
      return null;
    }
    return (
      <a href={createNewUrl} class="btn action btn-primary font-icon-plus-thin" target="_blank">New</a>
    );
  }
  render() {
    const {
      lazyLoad,
      options,
      creatable,
      ...passThroughAttributes
    } = this.props;

    const optionAttributes = lazyLoad
      ? { loadOptions: this.getOptions }
      : { options };
    const filterOption = () => true;

    let SelectComponent = Select;
    if (lazyLoad && creatable) {
      SelectComponent = Select.AsyncCreatable;
    } else if (lazyLoad) {
      SelectComponent = Select.Async;
    } else if (creatable) {
      SelectComponent = Select.Creatable;
    }

    // Update the value to passthrough with the kept state provided this component is not
    // "controlled"
    if (!this.isControlled()) {
      passThroughAttributes.value = this.state.value;
    }

    return (
      <div className="inputs-container">
        <SelectComponent
          {...passThroughAttributes}
          onChange={this.handleChange}
          onBlur={this.handleOnBlur}
          filterOption={filterOption}
          inputProps={{ className: 'no-change-track' }}
          {...optionAttributes}
        />
        { this.renderCreateNew() }
      </div>
    );
  }
}

RelPickerField.propTypes = {
  name: PropTypes.string.isRequired,
  labelKey: PropTypes.string.isRequired,
  valueKey: PropTypes.string.isRequired,
  lazyLoad: PropTypes.bool,
  creatable: PropTypes.bool,
  multi: PropTypes.bool,
  disabled: PropTypes.bool,
  options: PropTypes.arrayOf(PropTypes.object),
  optionUrl: PropTypes.string,
  value: PropTypes.any,
  onChange: PropTypes.func,
  onBlur: PropTypes.func,
};

RelPickerField.defaultProps = {
  labelKey: 'Title',
  valueKey: 'Value',
  disabled: false,
  lazyLoad: false,
  creatable: false,
  multi: false,
};

export { RelPickerField as Component };

export default fieldHolder(RelPickerField);
