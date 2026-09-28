import { Controller } from '@hotwired/stimulus';

// Shows the image picked in a file input (theme block file_widget).
export default class extends Controller {
    static targets = ['input', 'image'];

    show() {
        const file = this.inputTarget.files[0];
        if (this.imageTarget.src.startsWith('blob:')) {
            URL.revokeObjectURL(this.imageTarget.src);
        }
        const isImage = file?.type.startsWith('image/');
        this.imageTarget.hidden = !isImage;
        this.imageTarget.src = isImage ? URL.createObjectURL(file) : 'data:,';
    }
}
