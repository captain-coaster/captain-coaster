import { Controller } from '@hotwired/stimulus';

// Shows the name and the image of the file picked in a file input (theme block file_widget).
export default class extends Controller {
    static targets = ['input', 'name', 'image'];

    show() {
        const file = this.inputTarget.files[0];
        this.nameTarget.textContent = file?.name ?? '';
        if (this.imageTarget.src.startsWith('blob:')) {
            URL.revokeObjectURL(this.imageTarget.src);
        }
        const isImage = file?.type.startsWith('image/');
        this.imageTarget.hidden = !isImage;
        this.imageTarget.src = isImage ? URL.createObjectURL(file) : 'data:,';
    }
}
