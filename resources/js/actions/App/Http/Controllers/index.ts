import DashboardController from './DashboardController'
import ClientController from './ClientController'
import Admin from './Admin'
import DocumentController from './DocumentController'
import DocumentAnnotationController from './DocumentAnnotationController'
import DocumentCommentController from './DocumentCommentController'
import MatterController from './MatterController'
import Settings from './Settings'

const Controllers = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    ClientController: Object.assign(ClientController, ClientController),
    Admin: Object.assign(Admin, Admin),
    DocumentController: Object.assign(DocumentController, DocumentController),
    DocumentAnnotationController: Object.assign(DocumentAnnotationController, DocumentAnnotationController),
    DocumentCommentController: Object.assign(DocumentCommentController, DocumentCommentController),
    MatterController: Object.assign(MatterController, MatterController),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers