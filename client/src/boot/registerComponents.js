import Injector from 'lib/Injector';
import RelPickerField from '../components/RelPickerField';

export default () => {
  Injector.component.registerMany({
    RelPickerField,
  });
};
