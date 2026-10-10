import { initializePhotoEditors } from './photos.js';

export function initializeWishlist(root) {
    initializePhotoEditors(root, '[data-wishlist-editor]');
}
