@extends('layouts.backend')

@section('title', __('system.types'))

@section('content')
<div id="data">
    <div class="actions row">
        <div class="col-sm-12 col-md-6 col-lg-8">
            <a class="btn btn-outline-primary" href="{{ route('backend.tickets.create') }}">
                <i class="fa fa-plus-circle" aria-hidden="true"></i> {{ __('system.new_entry') }}
            </a>
        </div>
        <div class="col-sm-12 col-md-6 col-lg-4">
            <div class="input-group">
                <input type="text" v-model="filterText" class="form-control" @keydown="doFilter"
                       placeholder="{{ __('system.search') }}">
                <div class="input-group-append">
                    <button class="btn btn-warning" @click="resetFilter">{{ __('system.reset') }}</button>
                </div>
            </div>
        </div>
    </div>
    <vuetable ref="vuetable" api-url="{{ route('backend.tickets.index') }}" :fields="fields" :append-params="param"
        pagination-path="" @vuetable:pagination-data="onPaginationData">
        <template slot="actions" scope="props">
            <div class="table-actions">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-warning" @click="editItem(props.rowData)">
                        <i class="fas fa-pencil-alt fa-fw" aria-hidden="true"></i>
                    </button>
                    <button class="btn btn-danger" @click="deleteItem(props.rowData)">
                        <i class="fas fa-trash fa-fw" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </template>
    </vuetable>
    <div class="vuetable-pagination row">
        <div class="col-sm-12 col-md-6">
            <vuetable-pagination-info ref="paginationInfo"></vuetable-pagination-info>
        </div>
        <div class="col-sm-12 col-md-6">
            <vuetable-pagination ref="pagination" @vuetable-pagination:change-page="onChangePage"></vuetable-pagination>
        </div>
    </div>
    <keep-alive>
        <div :is="modalView" :row-data="rowData" @modal-closed="clearModal" @exception-occured="showException"></div>
    </keep-alive>
</div>
@endsection

@push('scripts')
<script type="text/html" id="details">
    <div class="modal fade" id="details-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"><i class="fa fa-pencil-alt" aria-hidden="true"></i> {{ __('system.edit') }}</h4>
                    <button type="button" class="close" @click="close" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        @include('partials.error')
                        <div class="row form-group">
                            <label class="col-sm-12 col-md-3 col-form-label">{{ __('system.type') }}</label>
                            <div class="col-sm-12 col-md-9">
                                <input type="text" name="name" id="name" class="form-control" v-model="editData.name" required>
                            </div>
                        </div>
                        <div class="row form-group">
                            <label class="col-sm-12 col-md-3 col-form-label">{{ __('system.price') }}</label>
                            <div class="col-sm-12 col-md-9">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">{{ Setting::get('payment.currency') }}</span>
                                    </div>
                                    <input name="amount" id="amount" class="form-control" type="number"
                                           v-model="editData.price" required>
                                </div>
                            </div>
                        </div>
                        <div class="row form-group">
                            <div class="col-sm-12 offset-md-3 col-md-9">
                                <button id="submit" type="submit" class="btn btn-warning" @click="submit">
                                    {{ __('system.update') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</script>
<script type="text/html" id="exception">
    <div class="modal fade" id="exception-modal" tabindex="-1" role="document">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">
                        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ __('system.exception_title') }}
                    </h4>
                    <button type="button" class="close" @click="close" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    {{ __('system.exception_message') }}
                </div>
            </div>
        </div>
    </div>
</script>
<script type="text/javascript">
    $(document).ready(function() {
        function processErrorBag(errorBag) {
            _.forEach(errorBag, function(messages, key) {
                let input = $(':input[name=' + key + ']');

                let formatted = '<ul>';
                _.forEach(messages, function(message) {
                    formatted += '<li>' + message + '</li>';
                });
                formatted += '</ul>';

                form.foundation('findFormError', input).first().html(formatted);
                form.foundation('addErrorClasses', input);
            });
        }

        Vue.component('details-modal', {
            template: '#details',
            data: function() {
                return {
                    editData: {
                        name: '',
                        price: '',
                    }
                };
            },
            props: ['rowData'],
            methods: {
                load: _.once(function() {
                    $('#details-modal').modal();
                }),
                close: function() {
                    $('#details-modal').modal('hide');
                    this.$emit('modal-closed');
                },
                submit: function() {
                    event.preventDefault();

                    let error = false;

                    $('#details-modal').find('form').first().on('forminvalid.zf.abide', function(event, form) {
                        error = true;
                    }).foundation('validateForm');

                    if (error === true) {
                        return;
                    }

                    let parent = this;

                    axios.put('/admin/tickets/' + this.$data.editData.id, this.$data.editData)
                        .then(function() {
                            $('#details-modal').html('<h3><i class="fa fa-check" aria-hidden="true"></i> '
                                + '{{ __('system.update_success') }}</h3><button class="close-button" @click="close"'
                                + ' type="button"><span aria-hidden="true">&times;</span></button>');
                            parent.$nextTick(function() {parent.$refs.vuetable.reload();});
                        })
                        .catch(function(error) {
                            if (error.response && error.response.status === '422') {
                                processErrorBag(error.response.data.errors);
                            } else {
                                $('#details-modal').modal('hide');
                                parent.$emit('exception-occured');
                            }
                        });
                }
            },
            activated: function(event) {
                this.$data.editData = this.$props.rowData;
                this.$nextTick();

                this.load();
                $('#details-modal').modal('show');
            }
        });

        Vue.component('exception-modal', {
            template: '#exception',
            methods: {
                load: _.once(function () {
                    $('#exception-modal').modal();
                }),
                close: function() {
                    $('#exception-modal').modal('hide');
                    this.$emit('modal-closed');
                },
            },
            activated: function(event) {
                this.load();
                $('#exception-modal').modal('show');
            }
        });

        const vm = new Vue({
            el: '#data',
            data: {
                modalView: '',
                rowData: '',
                filterText: '',
                fields: [
                    {
                        name: 'id',
                        visible: false,
                    },
                    {
                        name: '__checkbox'
                    },
                    {
                        name: 'name',
                        sortField: 'name',
                        title: '{{ __('system.type') }}'
                    },
                    {
                        name: 'full_price',
                        sortField: 'price',
                        title: '{{ __('system.price') }}'
                    },
                    {
                        name: '__slot:actions',
                        title: ''
                    }
                ],
                param: {}
            },
            methods: {
                onPaginationData: function(paginationData) {
                    this.$refs.pagination.setPaginationData(paginationData);
                    this.$refs.paginationInfo.setPaginationData(paginationData);
                },
                onChangePage: function(page) {
                    this.$refs.vuetable.changePage(page);
                },
                doFilter: _.debounce(function() {
                    this.$data.param = {
                        q: this.$data.filterText
                    };
                    this.$nextTick(function() {this.$refs.vuetable.refresh();});
                }, 250),
                resetFilter: function() {
                    this.$data.filterText = '';
                    this.$data.param = {};
                    this.$nextTick(function() {this.$refs.vuetable.refresh();});
                },
                editItem: function(data) {
                    this.$data.rowData = data;
                    this.$data.modalView = 'details-modal';
                    this.$nextTick();
                },
                deleteItem: function(data) {
                    axios.delete('/admin/tickets/' + data.id)
                        .then(function() {
                            this.$refs.vuetable.reload();
                        })
                        .catch(function(error) {
                            alert(error.response.data);
                        });
                },
                clearModal: function() {
                    this.$data.rowData = {};
                    this.$data.modalView = '';
                    this.$nextTick();
                },
                showException: function() {
                    this.$data.modalView = 'exception-modal';
                    this.$nextTick();
                }
            }
        });
    });
</script>
@endpush
